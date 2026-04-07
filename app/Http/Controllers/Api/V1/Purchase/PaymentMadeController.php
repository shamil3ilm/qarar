<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PaymentMadeResource;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Services\Purchase\PaymentMadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMadeController extends Controller
{
    public function __construct(
        private PaymentMadeService $paymentMadeService
    ) {
    }

    /**
     * List payments made with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMade::with(['supplier', 'bankAccount', 'allocations.bill'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->supplier_id, fn($q, $id) => $q->forSupplier($id))
            ->when($request->payment_method, fn($q, $method) => $q->where('payment_method', $method))
            ->when($request->start_date, fn($q, $date) => $q->where('payment_date', '>=', $date))
            ->when($request->end_date, fn($q, $date) => $q->where('payment_date', '<=', $date))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['payment_number', 'payment_date', 'amount', 'status', 'created_at', 'updated_at'], 'payment_date'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $payments = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($payments, PaymentMadeResource::class);
    }

    /**
     * Store a new payment made.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:contacts,id',
            'payment_number' => 'nullable|string|max:50',
            'payment_date' => 'nullable|date',
            'branch_id' => 'nullable|exists:branches,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card,online,other',
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => 'required|exists:bills,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        // Default payment_date to today if not provided
        $validated['payment_date'] = $validated['payment_date'] ?? now()->toDateString();

        // Validate allocation totals don't exceed payment amount
        if (!empty($validated['allocations'])) {
            $totalAllocated = collect($validated['allocations'])->sum('amount');
            if ($totalAllocated > (float) $validated['amount']) {
                return $this->error('Total allocation amount cannot exceed payment amount.', 'VALIDATION_ERROR', 422);
            }
        }

        try {
            $payment = $this->paymentMadeService->create(
                collect($validated)->except('allocations')->toArray(),
                $validated['allocations'] ?? []
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new PaymentMadeResource($payment), 'Payment created successfully.');
    }

    /**
     * Show a specific payment made.
     */
    public function show(PaymentMade $paymentMade): JsonResponse
    {
        return $this->success(new PaymentMadeResource(
            $paymentMade->load(['supplier', 'bankAccount', 'allocations.bill', 'journalEntry.lines'])
        ));
    }

    /**
     * Delete a pending payment.
     */
    public function destroy(PaymentMade $paymentMade): JsonResponse
    {
        if (!$paymentMade->isEditable()) {
            return $this->error('Only pending payments can be deleted.', 'VALIDATION_ERROR', 422);
        }

        $paymentMade->allocations()->delete();
        $paymentMade->delete();

        return $this->success(null, 'Payment deleted successfully.');
    }

    /**
     * Complete/confirm a payment.
     */
    public function complete(PaymentMade $paymentMade): JsonResponse
    {
        try {
            $payment = $this->paymentMadeService->complete($paymentMade, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PaymentMadeResource($payment), 'Payment completed successfully.');
    }

    /**
     * Void a payment.
     */
    public function void(Request $request, PaymentMade $paymentMade): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->paymentMadeService->void($paymentMade, $validated['reason'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PaymentMadeResource($payment), 'Payment voided successfully.');
    }

    /**
     * Allocate payment to bills.
     */
    public function allocate(Request $request, PaymentMade $paymentMade): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => 'nullable|exists:bills,id',
            'amount' => 'nullable|numeric|min:0.01',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => 'required|exists:bills,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            // Support both formats: flat (bill_id + amount) and array (allocations)
            if (!empty($validated['allocations'])) {
                // Validate supplier match and total amount
                $totalAllocated = 0;
                foreach ($validated['allocations'] as $allocationData) {
                    $bill = Bill::findOrFail($allocationData['bill_id']);

                    // Validate supplier match
                    if ($bill->supplier_id !== $paymentMade->supplier_id) {
                        return $this->error('Cannot allocate payment to bills from a different supplier.', 'VALIDATION_ERROR', 422);
                    }

                    $totalAllocated += (float) $allocationData['amount'];
                }

                // Validate total doesn't exceed unallocated amount
                $available = $paymentMade->getUnallocatedAmount();
                if ($totalAllocated > $available) {
                    return $this->error("Cannot allocate {$totalAllocated}. Only {$available} available.", 'VALIDATION_ERROR', 422);
                }

                foreach ($validated['allocations'] as $allocationData) {
                    $bill = Bill::findOrFail($allocationData['bill_id']);
                    $this->paymentMadeService->allocate(
                        $paymentMade,
                        $bill,
                        (float) $allocationData['amount']
                    );
                }
            } else {
                if (empty($validated['bill_id']) || empty($validated['amount'])) {
                    return $this->error('Either bill_id and amount, or allocations array is required.', 'VALIDATION_ERROR', 422);
                }

                $bill = Bill::findOrFail($validated['bill_id']);

                // Validate supplier match
                if ($bill->supplier_id !== $paymentMade->supplier_id) {
                    return $this->error('Cannot allocate payment to bills from a different supplier.', 'VALIDATION_ERROR', 422);
                }

                $this->paymentMadeService->allocate(
                    $paymentMade,
                    $bill,
                    (float) $validated['amount']
                );
            }
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PaymentMadeResource($paymentMade->fresh(['allocations.bill'])), 'Payment allocated successfully.');
    }

    /**
     * Get supplier statement.
     */
    public function supplierStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:contacts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $statement = $this->paymentMadeService->getSupplierStatement(
            (int) $validated['supplier_id'],
            isset($validated['start_date']) ? new \DateTime($validated['start_date']) : null,
            isset($validated['end_date']) ? new \DateTime($validated['end_date']) : null
        );

        return $this->success($statement);
    }

    /**
     * Get payments summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = PaymentMade::query();

        if ($request->supplier_id) {
            $query->forSupplier($request->supplier_id);
        }

        $pending = (clone $query)->pending()->count();
        $completed = (clone $query)->completed()->count();

        $pendingValue = (clone $query)->pending()->sum('amount');
        $completedValue = (clone $query)->completed()->sum('amount');

        $thisMonth = (clone $query)->completed()
            ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return $this->success([
            'total_count' => $query->count(),
            'pending_count' => $pending,
            'completed_count' => $completed,
            'pending_value' => (float) $pendingValue,
            'completed_value' => (float) $completedValue,
            'this_month_value' => (float) $thisMonth,
        ]);
    }
}

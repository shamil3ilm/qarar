<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorAdvanceRequestResource;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorAdvancePayment;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Services\Purchase\VendorAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAdvanceController extends Controller
{
    public function __construct(
        private VendorAdvanceService $vendorAdvanceService
    ) {}

    /**
     * List vendor advance requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VendorAdvanceRequest::with(['contact', 'requester', 'approver', 'purchaseOrder'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->contact_id, fn($q, $id) => $q->where('contact_id', $id))
            ->when($request->purchase_order_id, fn($q, $id) => $q->where('purchase_order_id', $id))
            ->when($request->search, function ($q, $search) {
                $q->where('request_number', 'like', "%{$search}%");
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['request_number', 'requested_amount', 'status', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)), VendorAdvanceRequestResource::class);
    }

    /**
     * Create a new vendor advance request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'request_number' => 'nullable|string|max:30',
            'requested_amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $advanceRequest = $this->vendorAdvanceService->createRequest($validated);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new VendorAdvanceRequestResource($advanceRequest), 'Advance request created successfully.');
    }

    /**
     * Show a vendor advance request.
     */
    public function show(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        return $this->success(
            new VendorAdvanceRequestResource(
                $vendorAdvance->load(['contact', 'purchaseOrder', 'requester', 'approver', 'payments.clearings.bill'])
            )
        );
    }

    /**
     * Approve a vendor advance request.
     */
    public function approve(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        try {
            $advanceRequest = $this->vendorAdvanceService->approveRequest($vendorAdvance);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new VendorAdvanceRequestResource($advanceRequest), 'Advance request approved successfully.');
    }

    /**
     * Record an advance payment.
     */
    public function recordPayment(Request $request, VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_account_id' => 'nullable|exists:accounts,id',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $payment = $this->vendorAdvanceService->recordPayment($vendorAdvance, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created($payment->toArray(), 'Advance payment recorded successfully.');
    }

    /**
     * Clear an advance payment against a bill.
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'advance_payment_id' => 'required|exists:vendor_advance_payments,id',
            'bill_id' => 'required|exists:bills,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $payment = VendorAdvancePayment::findOrFail($validated['advance_payment_id']);
        $bill = Bill::findOrFail($validated['bill_id']);

        if ($payment->advanceRequest->organization_id !== auth()->user()->organization_id) {
            return $this->error('Advance payment not found.', 'NOT_FOUND', 404);
        }

        if ($bill->organization_id !== auth()->user()->organization_id) {
            return $this->error('Bill not found.', 'NOT_FOUND', 404);
        }

        try {
            $clearing = $this->vendorAdvanceService->clearAgainstBill($payment, $bill, (float) $validated['amount']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created($clearing->toArray(), 'Advance cleared against bill successfully.');
    }

    /**
     * List clearings for a vendor advance request.
     */
    public function indexClearings(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        $clearings = $vendorAdvance->payments()
            ->with(['clearings.bill'])
            ->get()
            ->flatMap(fn($p) => $p->clearings);

        return $this->success($clearings->values()->toArray());
    }
}

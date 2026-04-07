<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\SupportsAgGrid;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\InvoiceResource;
use App\Models\Sales\Invoice;
use App\Services\Compliance\CompliPayClient;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use SupportsAgGrid;
    public function __construct(
        private InvoiceService $invoiceService,
        private CompliPayClient $compliPayClient
    ) {}

    /**
     * List invoices with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'salesperson'])
            ->latest('invoice_date')
            ->when($request->customer_id, fn($q, $id) => $q->forCustomer((int) $id))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->type, fn($q, $v) => $q->ofType($v))
            ->when(
                $request->input('from_date', $request->input('start_date')),
                fn($q, $v) => $q->where('invoice_date', '>=', $v)
            )
            ->when(
                $request->input('to_date', $request->input('end_date')),
                fn($q, $v) => $q->where('invoice_date', '<=', $v)
            )
            ->when($request->boolean('overdue', false), fn($q) => $q->overdue())
            ->when($request->boolean('unpaid', false), fn($q) => $q->unpaid());

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($query, $request);
        }

        $invoices = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($invoices, InvoiceResource::class);
    }

    /**
     * Create a new invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type' => 'nullable|in:standard,simplified,credit_note,debit_note',
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', auth()->user()->organization_id)],
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'place_of_supply' => 'nullable|string|max:2',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', auth()->user()->organization_id)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => 'nullable|integer|exists:units_of_measure,id',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_category_id' => 'nullable|integer|exists:tax_categories,id',
            'lines.*.account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.hsn_code' => 'nullable|string|max:20',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.cgst_rate' => 'nullable|numeric|min:0',
            'lines.*.sgst_rate' => 'nullable|numeric|min:0',
            'lines.*.igst_rate' => 'nullable|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->create(
                collect($validated)->except('lines')->toArray(),
                $validated['lines']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new InvoiceResource($invoice), 'Invoice created successfully.');
    }

    /**
     * Show an invoice.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'customer',
            'lines.product',
            'lines.variant',
            'salesperson',
            'journalEntry.lines',
            'paymentAllocations.payment',
        ]);

        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Update a draft invoice.
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'nullable|integer',
            'customer_id' => ['sometimes', 'integer', Rule::exists('contacts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'invoice_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'place_of_supply' => 'nullable|string|max:2',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', auth()->user()->organization_id)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => 'nullable|integer|exists:units_of_measure,id',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_category_id' => 'nullable|integer|exists:tax_categories,id',
            'lines.*.account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.hsn_code' => 'nullable|string|max:20',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $invoice = $this->invoiceService->update(
                $invoice,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\App\Exceptions\ConcurrencyException $e) {
            return $this->error($e->getMessage(), 'CONCURRENCY_ERROR', 409);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully.');
    }

    /**
     * Send/post an invoice.
     */
    public function send(Invoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->send($invoice);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success(new InvoiceResource($invoice), 'Invoice sent successfully.');
    }

    /**
     * Void an invoice.
     */
    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $invoice = $this->invoiceService->void($invoice, $request->input('reason', ''));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success(new InvoiceResource($invoice), 'Invoice voided successfully.');
    }

    /**
     * Create a credit note.
     */
    public function createCreditNote(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_category_id' => 'nullable|integer|exists:tax_categories,id',
        ]);

        try {
            $creditNote = $this->invoiceService->createCreditNote(
                $invoice,
                $validated['lines'],
                $validated['reason'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new InvoiceResource($creditNote), 'Credit note created successfully.');
    }

    /**
     * Get compliance status.
     */
    public function complianceStatus(Invoice $invoice): JsonResponse
    {
        if (!$invoice->compliance_uuid) {
            return $this->success([
                'status' => $invoice->compliance_status,
                'message' => 'Not submitted to compliance system.',
            ]);
        }

        try {
            $result = $this->compliPayClient->getStatus($invoice->compliance_uuid);

            return $this->success([
                'status' => $result->status,
                'uuid' => $invoice->compliance_uuid,
                'qr_code' => $invoice->compliance_qr_code,
                'submitted_at' => $invoice->compliance_submitted_at?->toISOString(),
            ]);
        } catch (\Exception $e) {
            return $this->success([
                'status' => $invoice->compliance_status,
                'uuid' => $invoice->compliance_uuid,
                'qr_code' => $invoice->compliance_qr_code,
                'submitted_at' => $invoice->compliance_submitted_at?->toISOString(),
            ]);
        }
    }

    /**
     * Delete a draft invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return $this->error('Only draft invoices can be deleted.', 'VALIDATION_ERROR', 422);
        }

        $invoice->delete();

        return $this->success(null, 'Invoice deleted successfully.');
    }

    /**
     * Get invoice summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->when($request->has('from_date'), fn($q) => $q->where('invoice_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('invoice_date', '<=', $request->input('to_date')));

        $stats = [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total'),
            'total_paid' => $query->sum('amount_paid'),
            'total_outstanding' => $query->sum('amount_due'),
            'by_status' => Invoice::selectRaw('status, COUNT(*) as count, SUM(total) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
            'overdue_count' => Invoice::overdue()->count(),
            'overdue_amount' => Invoice::overdue()->sum('amount_due'),
        ];

        return $this->success($stats);
    }
}

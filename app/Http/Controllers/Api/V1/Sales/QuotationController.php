<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\QuotationResource;
use App\Models\Core\NumberSequence;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    /**
     * List quotations with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::with(['customer', 'salesperson'])
            ->latest('quotation_date')
            ->when($request->has('customer_id'), fn($q) => $q->forCustomer($request->integer('customer_id')))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('from_date'), fn($q) => $q->where('quotation_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('quotation_date', '<=', $request->input('to_date')));

        $quotations = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($quotations, QuotationResource::class);
    }

    /**
     * Create a new quotation.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'quotation_date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:quotation_date',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
        ]);

        $user = $request->user();
        $organizationId = $user->organization_id;
        $branchId = $validated['branch_id']
            ?? $request->attributes->get('branch')?->id
            ?? $user->getDefaultBranch()?->id;

        $quotation = DB::transaction(function () use ($validated, $organizationId, $branchId, $user) {
            try {
                $quotationNumber = NumberSequence::getNext($organizationId, 'quotation', $branchId);
            } catch (\Exception $e) {
                // Fallback to simple number generation if NumberSequence schema is incomplete
                Log::warning('NumberSequence unavailable for quotation; using fallback counter', [
                    'organization_id' => $organizationId,
                    'error' => $e->getMessage(),
                ]);
                $count = \App\Models\Sales\Quotation::where('organization_id', $organizationId)->count() + 1;
                $quotationNumber = 'QUO-' . date('Y') . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
            }

            // Populate customer details from contact
            $customer = \App\Models\Sales\Contact::find($validated['customer_id']);

            $quotation = Quotation::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'quotation_number' => $quotationNumber,
                'customer_id' => $validated['customer_id'],
                'customer_name' => $customer?->getDisplayName() ?? $customer?->company_name ?? $customer?->first_name ?? 'Customer',
                'customer_email' => $customer?->email,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'],
                'currency_code' => $validated['currency_code'] ?? $user->organization->base_currency ?? 'SAR',
                'exchange_rate' => $validated['exchange_rate'] ?? 1.0000,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'salesperson_id' => $validated['salesperson_id'] ?? $user->id,
                'notes' => $validated['notes'] ?? null,
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => $user->id,
            ]);

            foreach ($validated['lines'] as $order => $lineData) {
                QuotationLine::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $lineData['product_id'] ?? null,
                    'variant_id' => $lineData['variant_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_id' => $lineData['unit_id'] ?? null,
                    'unit_price' => $lineData['unit_price'],
                    'discount_type' => $lineData['discount_type'] ?? null,
                    'discount_value' => $lineData['discount_value'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_category_id' => $lineData['tax_category_id'] ?? null,
                    'line_order' => $order + 1,
                ]);
            }

            $quotation->recalculateTotals();
            $quotation->load('lines');

            return $quotation;
        });

        return $this->created(new QuotationResource($quotation), 'Quotation created successfully.');
    }

    /**
     * Show a quotation.
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $quotation->load([
            'customer',
            'lines.product',
            'lines.variant',
            'salesperson',
        ]);

        return $this->success(new QuotationResource($quotation));
    }

    /**
     * Update a quotation.
     */
    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        if (!$quotation->isEditable()) {
            return $this->error(
                'Quotation cannot be updated in its current status.',
                'VALIDATION_ERROR',
                422
            );
        }

        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'quotation_date' => 'sometimes|date',
            'valid_until' => 'sometimes|date|after_or_equal:quotation_date',
            'currency_code' => 'sometimes|string|size:3',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update(
                collect($validated)->except('lines')->filter(fn ($v) => $v !== null)->toArray()
            );

            if (isset($validated['lines'])) {
                $quotation->lines()->delete();

                foreach ($validated['lines'] as $order => $lineData) {
                    QuotationLine::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $lineData['product_id'] ?? null,
                        'variant_id' => $lineData['variant_id'] ?? null,
                        'description' => $lineData['description'],
                        'quantity' => $lineData['quantity'],
                        'unit_id' => $lineData['unit_id'] ?? null,
                        'unit_price' => $lineData['unit_price'],
                        'discount_type' => $lineData['discount_type'] ?? null,
                        'discount_value' => $lineData['discount_value'] ?? 0,
                        'tax_rate' => $lineData['tax_rate'] ?? 0,
                        'tax_category_id' => $lineData['tax_category_id'] ?? null,
                        'line_order' => $order + 1,
                    ]);
                }

                $quotation->recalculateTotals();
            }
        });

        $quotation->load(['customer', 'lines', 'salesperson']);

        return $this->success(new QuotationResource($quotation), 'Quotation updated successfully.');
    }

    /**
     * Delete a draft quotation.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return $this->error(
                'Only draft quotations can be deleted.',
                'VALIDATION_ERROR',
                422
            );
        }

        $quotation->delete();

        return $this->success(null, 'Quotation deleted successfully.');
    }

    /**
     * Send a quotation (change status to sent).
     */
    public function send(Quotation $quotation): JsonResponse
    {
        if (!in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_EXPIRED])) {
            return $this->error(
                'Quotation cannot be sent in its current status.',
                'VALIDATION_ERROR',
                422
            );
        }

        $quotation->update(['status' => Quotation::STATUS_SENT]);
        $quotation->load(['customer', 'lines', 'salesperson']);

        return $this->success(new QuotationResource($quotation), 'Quotation sent successfully.');
    }

    /**
     * Accept or decline a quotation.
     * POST /quotations/{id}/review  {"action": "accept"|"decline"}
     */
    public function review(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        if (!in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT])) {
            return $this->error(
                'Quotation cannot be reviewed in its current status.',
                'VALIDATION_ERROR',
                422
            );
        }

        $quotation->update([
            'status' => $validated['action'] === 'accept'
                ? Quotation::STATUS_ACCEPTED
                : Quotation::STATUS_DECLINED,
        ]);
        $quotation->load(['customer', 'lines', 'salesperson']);

        $message = $validated['action'] === 'accept'
            ? 'Quotation accepted successfully.'
            : 'Quotation declined successfully.';

        return $this->success(new QuotationResource($quotation), $message);
    }

    /**
     * Convert an accepted quotation to invoice or sales order.
     */
    public function convert(Request $request, Quotation $quotation): JsonResponse
    {
        if (!$quotation->canBeConverted()) {
            return $this->error(
                'Only accepted quotations can be converted.',
                'VALIDATION_ERROR',
                422
            );
        }

        $validated = $request->validate([
            'convert_to' => 'required|in:invoice,sales_order',
        ]);

        $result = DB::transaction(function () use ($quotation, $validated, $request) {
            $convertTo = $validated['convert_to'];

            if ($convertTo === 'invoice') {
                $invoice = app(InvoiceService::class)->createFromQuotation($quotation, $request->all());

                return ['type' => 'invoice', 'id' => $invoice->id, 'number' => $invoice->invoice_number];
            }

            if ($convertTo === 'sales_order') {
                try {
                    $soNumber = NumberSequence::getNext(
                        $quotation->organization_id,
                        'sales_order',
                        $quotation->branch_id
                    );
                } catch (\Exception $e) {
                    $count = SalesOrder::where('organization_id', $quotation->organization_id)->count() + 1;
                    $soNumber = 'SO-' . date('Y') . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
                }

                $salesOrder = SalesOrder::create([
                    'organization_id' => $quotation->organization_id,
                    'branch_id' => $quotation->branch_id,
                    'order_number' => $soNumber,
                    'customer_id' => $quotation->customer_id,
                    'customer_name' => $quotation->customer_name ?: ($quotation->customer?->getDisplayName() ?? 'Customer'),
                    'customer_email' => $quotation->customer_email,
                    'order_date' => now(),
                    'currency_code' => $quotation->currency_code,
                    'exchange_rate' => $quotation->exchange_rate,
                    'subtotal' => $quotation->subtotal,
                    'discount_type' => $quotation->discount_type,
                    'discount_value' => $quotation->discount_value,
                    'discount_amount' => $quotation->discount_amount,
                    'tax_amount' => $quotation->tax_amount,
                    'total' => $quotation->total,
                    'salesperson_id' => $quotation->salesperson_id,
                    'notes' => $quotation->notes,
                    'reference' => $quotation->quotation_number,
                    'quotation_id' => $quotation->id,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);

                foreach ($quotation->lines as $order => $line) {
                    SalesOrderLine::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $line->product_id,
                        'variant_id' => $line->variant_id,
                        'description' => $line->description,
                        'quantity' => $line->quantity,
                        'quantity_delivered' => 0,
                        'quantity_invoiced' => 0,
                        'unit_price' => $line->unit_price,
                        'discount_amount' => $line->discount_amount,
                        'tax_rate' => $line->tax_rate,
                        'tax_amount' => $line->tax_amount,
                        'subtotal' => $line->subtotal,
                        'total' => $line->total,
                        'line_order' => $line->line_order,
                    ]);
                }

                $salesOrder->recalculateTotals();

                $quotation->update(['status' => Quotation::STATUS_CONVERTED]);

                return ['type' => 'sales_order', 'id' => $salesOrder->id, 'number' => $salesOrder->order_number];
            }

            return null;
        });

        return $this->success($result, 'Quotation converted successfully.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Core\NumberSequence;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\Accounting\CreditManagementService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\SalesOrderDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly CreditManagementService $creditService
    ) {}

    /**
     * List sales orders with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesOrder::with(['customer', 'salesperson', 'warehouse'])
            ->latest('order_date')
            ->when($request->customer_id, fn($q, $id) => $q->forCustomer((int) $id))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->from_date, fn($q, $v) => $q->where('order_date', '>=', $v))
            ->when($request->to_date, fn($q, $v) => $q->where('order_date', '<=', $v))
            ->when($request->salesperson_id, fn($q, $id) => $q->where('salesperson_id', (int) $id))
            ->when($request->warehouse_id, fn($q, $id) => $q->where('warehouse_id', (int) $id))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            });

        $salesOrders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($salesOrders);
    }

    /**
     * Create a new sales order.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'delivery_instructions' => 'nullable|string|max:2000',
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
            'lines.*.warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
        ]);

        $user = $request->user();
        $organizationId = $user->organization_id;
        $branchId = $validated['branch_id']
            ?? $request->attributes->get('branch')?->id
            ?? $user->getDefaultBranch()?->id;

        $salesOrder = DB::transaction(function () use ($validated, $organizationId, $branchId, $user) {
            try {
                $orderNumber = NumberSequence::getNext($organizationId, 'sales_order', $branchId);
            } catch (\Exception $e) {
                Log::warning('NumberSequence unavailable for sales_order; using fallback counter', [
                    'organization_id' => $organizationId,
                    'error' => $e->getMessage(),
                ]);
                $count = SalesOrder::where('organization_id', $organizationId)->count() + 1;
                $orderNumber = 'SO-' . date('Y') . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
            }

            $customer = Contact::find($validated['customer_id']);

            $salesOrder = SalesOrder::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'order_number' => $orderNumber,
                'customer_id' => $validated['customer_id'],
                'customer_name' => $customer?->getDisplayName() ?? $customer?->company_name ?? 'Customer',
                'customer_email' => $customer?->email,
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'currency_code' => $validated['currency_code'] ?? $user->organization->base_currency ?? 'SAR',
                'exchange_rate' => $validated['exchange_rate'] ?? 1.0000,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'salesperson_id' => $validated['salesperson_id'] ?? $user->id,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'delivery_instructions' => $validated['delivery_instructions'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'status' => SalesOrder::STATUS_DRAFT,
                'created_by' => $user->id,
            ]);

            foreach ($validated['lines'] as $order => $lineData) {
                SalesOrderLine::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $lineData['product_id'] ?? null,
                    'variant_id' => $lineData['variant_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'quantity_delivered' => 0,
                    'quantity_invoiced' => 0,
                    'unit_id' => $lineData['unit_id'] ?? null,
                    'unit_price' => $lineData['unit_price'],
                    'discount_type' => $lineData['discount_type'] ?? null,
                    'discount_value' => $lineData['discount_value'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_category_id' => $lineData['tax_category_id'] ?? null,
                    'warehouse_id' => $lineData['warehouse_id'] ?? null,
                    'line_order' => $order + 1,
                ]);
            }

            $salesOrder->recalculateTotals();
            $salesOrder->load('lines');

            return $salesOrder;
        });

        return $this->created($salesOrder, 'Sales order created successfully.');
    }

    /**
     * Show a sales order with lines.
     */
    public function show(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->load([
            'customer',
            'lines.product',
            'lines.variant',
            'lines.warehouse',
            'salesperson',
            'warehouse',
            'quotation',
            'invoices',
        ]);

        $data = $salesOrder->toArray();
        $data['fulfillment_progress'] = $salesOrder->getFulfillmentProgress();

        return $this->success($data);
    }

    /**
     * Update a draft sales order.
     */
    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->error(
                'Only draft sales orders can be updated.',
                'VALIDATION_ERROR',
                422
            );
        }

        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'order_date' => 'sometimes|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => 'sometimes|string|size:3',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'delivery_instructions' => 'nullable|string|max:2000',
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
            'lines.*.warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
        ]);

        DB::transaction(function () use ($salesOrder, $validated) {
            $orderData = collect($validated)->except('lines')->toArray();

            if (isset($validated['customer_id']) && $validated['customer_id'] !== $salesOrder->customer_id) {
                $customer = Contact::find($validated['customer_id']);
                $orderData['customer_name'] = $customer?->getDisplayName() ?? $customer?->company_name ?? 'Customer';
                $orderData['customer_email'] = $customer?->email;
            }

            $salesOrder->update($orderData);

            if (isset($validated['lines'])) {
                $salesOrder->lines()->delete();

                foreach ($validated['lines'] as $order => $lineData) {
                    SalesOrderLine::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $lineData['product_id'] ?? null,
                        'variant_id' => $lineData['variant_id'] ?? null,
                        'description' => $lineData['description'],
                        'quantity' => $lineData['quantity'],
                        'quantity_delivered' => 0,
                        'quantity_invoiced' => 0,
                        'unit_id' => $lineData['unit_id'] ?? null,
                        'unit_price' => $lineData['unit_price'],
                        'discount_type' => $lineData['discount_type'] ?? null,
                        'discount_value' => $lineData['discount_value'] ?? 0,
                        'tax_rate' => $lineData['tax_rate'] ?? 0,
                        'tax_category_id' => $lineData['tax_category_id'] ?? null,
                        'warehouse_id' => $lineData['warehouse_id'] ?? null,
                        'line_order' => $order + 1,
                    ]);
                }

                $salesOrder->recalculateTotals();
            }
        });

        $salesOrder->load(['customer', 'lines', 'salesperson', 'warehouse']);

        return $this->success($salesOrder, 'Sales order updated successfully.');
    }

    /**
     * Delete a draft sales order.
     */
    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->error(
                'Only draft sales orders can be deleted.',
                'VALIDATION_ERROR',
                422
            );
        }

        $salesOrder->lines()->delete();
        $salesOrder->delete();

        return $this->success(null, 'Sales order deleted successfully.');
    }

    /**
     * Confirm a draft sales order.
     */
    public function confirm(SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->error(
                'Only draft sales orders can be confirmed.',
                'VALIDATION_ERROR',
                422
            );
        }

        if ($salesOrder->lines()->count() === 0) {
            return $this->error(
                'Sales order must have at least one line item.',
                'VALIDATION_ERROR',
                422
            );
        }

        // SAP SD credit check at order confirmation (VKM1/VKM3 equivalent).
        // We check here (not just at invoicing) so sales reps get early warning.
        $customer    = $salesOrder->customer;
        $orderAmount = (float) $salesOrder->total;

        if ($customer && ! $this->creditService->checkCreditLimit($customer, $orderAmount)) {
            return $this->error(
                "Customer '{$customer->getDisplayName()}' has exceeded their credit limit. Release the credit hold or obtain approval before confirming.",
                'CREDIT_LIMIT_EXCEEDED',
                422
            );
        }

        $salesOrder->transitionTo(SalesOrder::STATUS_CONFIRMED);
        $salesOrder->load(['customer', 'lines', 'salesperson']);

        return $this->success($salesOrder, 'Sales order confirmed successfully.');
    }

    /**
     * Return the real-time credit exposure for the customer of this order.
     * Equivalent to SAP SD credit check (FD32 / VKM1 pre-check).
     *
     * GET /sales-orders/{salesOrder}/credit-check
     */
    public function creditCheck(SalesOrder $salesOrder): JsonResponse
    {
        $customer = $salesOrder->customer;

        if (! $customer) {
            return $this->error('Order has no customer assigned.', 'NO_CUSTOMER', 422);
        }

        $exposure    = $this->creditService->getCreditExposure($customer);
        $orderAmount = (float) $salesOrder->total;
        $available   = (float) $exposure['available_credit'];

        $onHold = \App\Models\Accounting\CreditHold::where('organization_id', $customer->organization_id)
            ->where('contact_id', $customer->id)
            ->whereNull('released_at')
            ->exists();

        return $this->success([
            'customer_id'      => $customer->id,
            'customer_name'    => $customer->getDisplayName(),
            'credit_limit'     => (float) $exposure['credit_limit'],
            'current_exposure' => (float) $exposure['total_exposure'],
            'available_credit' => $available,
            'utilization_pct'  => (float) $exposure['utilization_pct'],
            'order_amount'     => $orderAmount,
            'will_exceed'      => ($available - $orderAmount) < 0,
            'on_credit_hold'   => $onHold,
            'currency_code'    => $exposure['currency_code'],
        ], 'Credit check completed.');
    }

    /**
     * Cancel a sales order.
     */
    public function cancel(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $allowedStatuses = [
            SalesOrder::STATUS_DRAFT,
            SalesOrder::STATUS_CONFIRMED,
            SalesOrder::STATUS_PROCESSING,
            SalesOrder::STATUS_PARTIALLY_DELIVERED,
        ];

        if (!in_array($salesOrder->status, $allowedStatuses, true)) {
            return $this->error(
                'Sales order cannot be cancelled in its current status.',
                'VALIDATION_ERROR',
                422
            );
        }

        $salesOrder->transitionTo(SalesOrder::STATUS_CANCELLED);
        $salesOrder->load(['customer', 'lines', 'salesperson']);

        return $this->success($salesOrder, 'Sales order cancelled successfully.');
    }

    /**
     * Convert a sales order to an invoice.
     */
    public function convertToInvoice(SalesOrder $salesOrder): JsonResponse
    {
        if (!$salesOrder->canBeInvoiced()) {
            return $this->error(
                'Sales order cannot be invoiced in its current status. Order must be partially delivered or delivered.',
                'VALIDATION_ERROR',
                422
            );
        }

        $invoice = app(InvoiceService::class)->createFromSalesOrder($salesOrder);

        $result = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ];

        return $this->created($result, 'Invoice created from sales order successfully.');
    }

    /**
     * Create a delivery goods issue for a confirmed sales order.
     *
     * POST /api/v1/sales/sales-orders/{salesOrder}/create-delivery
     */
    public function createDelivery(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'line_ids'     => ['nullable', 'array'],
            'line_ids.*'   => ['integer'],
        ]);

        try {
            $gi = app(SalesOrderDeliveryService::class)->createDeliveryGoodsIssue(
                order: $salesOrder,
                warehouseId: (int) $validated['warehouse_id'],
                userId: auth()->id(),
                lineIds: $validated['line_ids'] ?? null,
            );

            return $this->created($gi, 'Delivery goods issue created and posted.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'DELIVERY_FAILED', 422);
        }
    }
}

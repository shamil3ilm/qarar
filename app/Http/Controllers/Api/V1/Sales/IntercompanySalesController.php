<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\IcBillingDocumentResource;
use App\Http\Resources\Sales\IntercompanySalesOrderResource;
use App\Models\Sales\IcBillingDocument;
use App\Models\Sales\IntercompanySalesOrder;
use App\Services\Sales\IntercompanySalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntercompanySalesController extends Controller
{
    public function __construct(
        private IntercompanySalesService $service
    ) {}

    /**
     * List intercompany sales orders.
     * Query params: selling_organization_id, buying_organization_id, status, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['selling_organization_id', 'buying_organization_id', 'status']);
        $perPage = $request->integer('per_page', 20);

        $paginator = $this->service->list($filters, $perPage);

        return $this->paginated($paginator, IntercompanySalesOrderResource::class);
    }

    /**
     * Create a new intercompany sales order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selling_organization_id'    => 'required|integer|exists:organizations,id',
            'buying_organization_id'     => 'required|integer|exists:organizations,id|different:selling_organization_id',
            'order_number'               => 'required|string|max:50',
            'order_date'                 => 'required|date',
            'requested_delivery_date'    => 'nullable|date|after_or_equal:order_date',
            'transfer_price_version_id'  => 'nullable|integer|exists:transfer_price_versions,id',
            'currency_code'              => 'nullable|string|size:3',
            'notes'                      => 'nullable|string|max:5000',
            'lines'                      => 'required|array|min:1',
            'lines.*.product_id'         => 'required|integer|exists:products,id',
            'lines.*.line_number'        => 'required|integer|min:1',
            'lines.*.description'        => 'nullable|string|max:500',
            'lines.*.quantity'           => 'required|numeric|min:0.0001',
            'lines.*.unit_of_measure'    => 'nullable|string|max:20',
            'lines.*.transfer_price'     => 'required|numeric|min:0',
            'lines.*.list_price'         => 'nullable|numeric|min:0',
            'lines.*.tax_rate'           => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['created_by'] = auth()->id();

        $order = $this->service->create($validated);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order created.', 201);
    }

    /**
     * Show an intercompany sales order with lines, PO link, and billing documents.
     */
    public function show(int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::with([
            'lines.product',
            'purchaseOrderLink',
            'billingDocuments',
            'sellingOrganization',
            'buyingOrganization',
            'createdBy',
        ])->findOrFail($id);

        return $this->success(new IntercompanySalesOrderResource($order));
    }

    /**
     * Update header fields of a draft intercompany sales order.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);

        $validated = $request->validate([
            'order_date'                 => 'sometimes|date',
            'requested_delivery_date'    => 'nullable|date',
            'transfer_price_version_id'  => 'nullable|integer|exists:transfer_price_versions,id',
            'currency_code'              => 'nullable|string|size:3',
            'notes'                      => 'nullable|string|max:5000',
        ]);

        $order = $this->service->update($order, $validated);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order updated.');
    }

    /**
     * Confirm a draft intercompany sales order.
     */
    public function confirm(int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);
        $order = $this->service->confirm($order);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order confirmed.');
    }

    /**
     * Link the buying org's purchase order to this IC sales order.
     * Body: { "purchase_order_id": 123 }
     */
    public function linkPurchaseOrder(Request $request, int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);

        $validated = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
        ]);

        $link = $this->service->linkPurchaseOrder($order, (int) $validated['purchase_order_id']);

        return $this->success($link, 'Purchase order linked.');
    }

    /**
     * Transition a confirmed order to in_delivery.
     */
    public function startDelivery(int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);
        $order = $this->service->startDelivery($order);

        return $this->success(new IntercompanySalesOrderResource($order), 'Delivery started.');
    }

    /**
     * Create a draft intercompany billing document (IV) for an order.
     */
    public function createBillingDocument(Request $request, int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);

        $validated = $request->validate([
            'document_number' => 'required|string|max:50',
            'billing_date'    => 'required|date',
            'currency_code'   => 'nullable|string|size:3',
            'subtotal'        => 'required|numeric|min:0',
            'tax_amount'      => 'nullable|numeric|min:0',
            'total_amount'    => 'required|numeric|min:0',
            'notes'           => 'nullable|string|max:5000',
        ]);

        $doc = $this->service->createBillingDocument($order, $validated);

        return $this->success(new IcBillingDocumentResource($doc), 'Billing document created.', 201);
    }

    /**
     * Post an intercompany billing document.
     */
    public function postBillingDocument(int|string $id, int|string $billingDocId): JsonResponse
    {
        // Verify the billing document belongs to this order
        $order = IntercompanySalesOrder::findOrFail($id);
        $doc   = IcBillingDocument::where('intercompany_sales_order_id', $order->id)
            ->findOrFail($billingDocId);

        $doc = $this->service->postBillingDocument($doc);

        return $this->success(new IcBillingDocumentResource($doc), 'Billing document posted.');
    }

    /**
     * Cancel an intercompany sales order.
     */
    public function cancel(int|string $id): JsonResponse
    {
        $order = IntercompanySalesOrder::findOrFail($id);
        $order = $this->service->cancel($order);

        return $this->success(new IntercompanySalesOrderResource($order), 'Intercompany sales order cancelled.');
    }
}

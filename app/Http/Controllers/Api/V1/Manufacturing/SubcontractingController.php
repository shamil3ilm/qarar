<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Manufacturing\SubcontractReceipt;
use App\Models\Manufacturing\SubcontractTransfer;
use App\Services\Manufacturing\SubcontractingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcontractingController extends Controller
{
    public function __construct(
        private SubcontractingService $subcontractingService,
    ) {}

    // -------------------------------------------------------------------------
    // Subcontract Orders
    // -------------------------------------------------------------------------

    /**
     * List subcontract orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SubcontractOrder::with(['vendor', 'branch'])
            ->withCount(['lines', 'components', 'transfers', 'receipts'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->contact_id, fn ($q, $id) => $q->forVendor($id))
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
            ->when($request->from_date, fn ($q, $d) => $q->whereDate('issued_date', '>=', $d))
            ->when($request->to_date, fn ($q, $d) => $q->whereDate('issued_date', '<=', $d))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['order_number', 'status', 'issued_date', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Create a subcontract order.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'             => 'required|exists:contacts,id',
            'issued_date'            => 'nullable|date',
            'expected_receipt_date'  => 'nullable|date|after_or_equal:issued_date',
            'currency_code'          => 'nullable|string|size:3',
            'service_charge'         => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string',
            'purchase_order_id'      => 'nullable|exists:purchase_orders,id',
            'branch_id'              => 'nullable|exists:branches,id',
            'lines'                  => 'required|array|min:1',
            'lines.*.product_id'     => 'required|exists:products,id',
            'lines.*.variant_id'     => 'nullable|exists:product_variants,id',
            'lines.*.ordered_quantity'    => 'required|numeric|min:0.0001',
            'lines.*.unit_id'             => 'required|exists:units_of_measure,id',
            'lines.*.unit_service_charge' => 'nullable|numeric|min:0',
            'components'                  => 'nullable|array',
            'components.*.product_id'     => 'required|exists:products,id',
            'components.*.variant_id'     => 'nullable|exists:product_variants,id',
            'components.*.required_quantity' => 'required|numeric|min:0.0001',
            'components.*.unit_id'           => 'required|exists:units_of_measure,id',
            'components.*.warehouse_id'      => 'required|exists:warehouses,id',
        ]);

        $order = $this->subcontractingService->createOrder($data);

        return $this->success($order, 'Subcontract order created.', 201);
    }

    /**
     * Show a single subcontract order.
     */
    public function show(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $subcontractOrder->loadMissing([
            'vendor',
            'branch',
            'lines.product',
            'lines.variant',
            'lines.unit',
            'components.product',
            'components.variant',
            'components.unit',
            'components.warehouse',
            'createdBy',
        ]);

        return $this->success($subcontractOrder);
    }

    /**
     * Update a draft subcontract order.
     */
    public function update(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        if (!$subcontractOrder->isDraft()) {
            return $this->error('Only draft orders can be updated.', 422);
        }

        $data = $request->validate([
            'contact_id'            => 'sometimes|exists:contacts,id',
            'issued_date'           => 'nullable|date',
            'expected_receipt_date' => 'nullable|date',
            'currency_code'         => 'nullable|string|size:3',
            'service_charge'        => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string',
            'purchase_order_id'     => 'nullable|exists:purchase_orders,id',
            'branch_id'             => 'nullable|exists:branches,id',
        ]);

        $subcontractOrder->update($data);

        return $this->success($subcontractOrder->fresh(), 'Order updated.');
    }

    /**
     * Send the order to the vendor (draft → sent).
     */
    public function sendToVendor(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->sendToVendor($subcontractOrder);

        return $this->success($order, 'Order sent to vendor.');
    }

    // -------------------------------------------------------------------------
    // Material Transfers
    // -------------------------------------------------------------------------

    /**
     * Transfer raw materials to the vendor.
     */
    public function transferMaterials(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $data = $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.component_id'     => 'required|integer',
            'items.*.quantity'         => 'required|numeric|min:0.0001',
            'items.*.batch_number'     => 'nullable|string|max:100',
        ]);

        $transfer = $this->subcontractingService->transferMaterialsToVendor(
            $subcontractOrder,
            $data['items']
        );

        return $this->success($transfer, 'Materials transferred to vendor.', 201);
    }

    /**
     * List transfers for an order.
     */
    public function indexTransfers(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $query = SubcontractTransfer::where('order_id', $subcontractOrder->id)
            ->with(['warehouse', 'lines.product', 'createdBy'])
            ->when($request->transfer_type, fn ($q, $t) => $q->where('transfer_type', $t))
            ->orderBy('transfer_date', 'desc');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Show a single transfer document.
     */
    public function showTransfer(SubcontractTransfer $transfer): JsonResponse
    {
        $transfer->loadMissing(['order', 'warehouse', 'lines.product', 'lines.unit', 'createdBy']);

        return $this->success($transfer);
    }

    // -------------------------------------------------------------------------
    // Receipts
    // -------------------------------------------------------------------------

    /**
     * Receive finished goods from the vendor.
     */
    public function receiveFromVendor(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id'                    => 'required|exists:warehouses,id',
            'receipt_date'                    => 'nullable|date',
            'notes'                           => 'nullable|string',
            'lines'                           => 'required|array|min:1',
            'lines.*.order_line_id'           => 'required|integer',
            'lines.*.quantity_received'       => 'required|numeric|min:0',
            'lines.*.quantity_rejected'       => 'nullable|numeric|min:0',
            'lines.*.unit_cost'               => 'nullable|numeric|min:0',
            'lines.*.batch_number'            => 'nullable|string|max:100',
            'lines.*.expiry_date'             => 'nullable|date',
        ]);

        $receipt = $this->subcontractingService->receiveFromVendor($subcontractOrder, $data);

        return $this->success($receipt, 'Goods received from vendor.', 201);
    }

    /**
     * List receipts for an order.
     */
    public function indexReceipts(Request $request, SubcontractOrder $subcontractOrder): JsonResponse
    {
        $query = SubcontractReceipt::where('order_id', $subcontractOrder->id)
            ->with(['warehouse', 'lines.product', 'createdBy'])
            ->orderBy('receipt_date', 'desc');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Show a single receipt document.
     */
    public function showReceipt(SubcontractReceipt $receipt): JsonResponse
    {
        $receipt->loadMissing(['order', 'warehouse', 'lines.product', 'lines.unit', 'createdBy']);

        return $this->success($receipt);
    }

    // -------------------------------------------------------------------------
    // Order Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Close the order and settle outstanding quantities.
     */
    public function closeOrder(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->closeOrder($subcontractOrder);

        return $this->success($order, 'Subcontract order closed.');
    }

    /**
     * Cancel a draft or sent order.
     */
    public function cancel(SubcontractOrder $subcontractOrder): JsonResponse
    {
        $order = $this->subcontractingService->cancel($subcontractOrder);

        return $this->success($order, 'Subcontract order cancelled.');
    }
}

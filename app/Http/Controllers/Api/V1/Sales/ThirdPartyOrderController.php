<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\ThirdPartyOrder;
use App\Services\Sales\ThirdPartyOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ThirdPartyOrderController extends Controller
{
    public function __construct(
        private ThirdPartyOrderService $thirdPartyOrderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->thirdPartyOrderService->list(
            $request->only(['status', 'vendor_id', 'sales_order_id']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales_order_id' => 'required|exists:sales_orders,id',
            'vendor_id' => 'required|exists:contacts,id',
            'shipping_address_line1' => 'nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_country_code' => 'nullable|string|size:2',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.sales_order_line_id' => 'nullable|exists:sales_order_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vendor_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $order = $this->thirdPartyOrderService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($order);
    }

    public function show(int $id): JsonResponse
    {
        $order = ThirdPartyOrder::with(['salesOrder', 'vendor', 'purchaseOrder', 'lines.product'])->findOrFail($id);

        return $this->success($order);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $order = ThirdPartyOrder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'shipping_address_line1' => 'nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_country_code' => 'nullable|string|size:2',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->update($order, $validator->validated());

        return $this->success($updated);
    }

    public function createPO(int $id): JsonResponse
    {
        $order = ThirdPartyOrder::with('lines')->findOrFail($id);

        if (!$order->canCreatePO()) {
            return $this->error(
                'Cannot create PO: order is not in pending status or PO already exists.',
                'INVALID_STATUS',
                422
            );
        }

        $po = $this->thirdPartyOrderService->createPurchaseOrder($order);

        return $this->created($po, 'Purchase order created successfully.');
    }

    public function confirmShipment(Request $request, int $id): JsonResponse
    {
        $order = ThirdPartyOrder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'shipping_confirmation' => 'nullable|string|max:100',
            'vendor_reference' => 'nullable|string|max:100',
            'estimated_delivery_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->confirmShipment($order, $validator->validated());

        return $this->success($updated, 'Shipment confirmed.');
    }

    public function confirmDelivery(Request $request, int $id): JsonResponse
    {
        $order = ThirdPartyOrder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'actual_delivery_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->thirdPartyOrderService->confirmDelivery(
            $order,
            $request->input('actual_delivery_date')
        );

        return $this->success($updated, 'Delivery confirmed.');
    }

    public function cancel(int $id): JsonResponse
    {
        $order = ThirdPartyOrder::findOrFail($id);
        $updated = $this->thirdPartyOrderService->cancel($order);

        return $this->success($updated, 'Third-party order cancelled.');
    }
}

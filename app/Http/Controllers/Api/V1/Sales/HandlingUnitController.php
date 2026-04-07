<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\HandlingUnit;
use App\Models\Sales\HandlingUnitItem;
use App\Services\Sales\HandlingUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HandlingUnitController extends Controller
{
    public function __construct(
        private HandlingUnitService $handlingUnitService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $units = $this->handlingUnitService->list(
            $request->only(['shipment_id', 'sales_order_id', 'hu_type', 'is_sealed']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($units);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'nullable|exists:shipments,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'hu_type' => 'nullable|in:box,pallet,container,bag,drum,other',
            'hu_number' => 'nullable|string|max:50',
            'sscc_number' => 'nullable|string|max:30',
            'gross_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.inventory_batch_id' => 'nullable|exists:inventory_batches,id',
            'items.*.sales_order_line_id' => 'nullable|exists:sales_order_lines,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.weight' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $hu = $this->handlingUnitService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($hu);
    }

    public function show(int $id): JsonResponse
    {
        $hu = HandlingUnit::with(['shipment', 'salesOrder', 'items.product', 'items.inventoryBatch'])->findOrFail($id);

        return $this->success($hu);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hu = HandlingUnit::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'hu_type' => 'nullable|in:box,pallet,container,bag,drum,other',
            'sscc_number' => 'nullable|string|max:30',
            'gross_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->handlingUnitService->update($hu, $validator->validated());

        return $this->success($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        HandlingUnit::findOrFail($id)->delete();

        return $this->noContent();
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $hu = HandlingUnit::findOrFail($id);

        if ($hu->is_sealed) {
            return $this->error('Cannot add items to a sealed handling unit.', 'SEALED', 422);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'inventory_batch_id' => 'nullable|exists:inventory_batches,id',
            'sales_order_line_id' => 'nullable|exists:sales_order_lines,id',
            'quantity' => 'required|numeric|min:0.0001',
            'weight' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $item = $this->handlingUnitService->addItem($hu, $validator->validated());

        return $this->created($item->load(['product', 'inventoryBatch']));
    }

    public function removeItem(int $id, int $itemId): JsonResponse
    {
        $hu = HandlingUnit::findOrFail($id);

        if ($hu->is_sealed) {
            return $this->error('Cannot remove items from a sealed handling unit.', 'SEALED', 422);
        }

        $this->handlingUnitService->removeItem($hu, $itemId);

        return $this->noContent();
    }

    public function seal(int $id): JsonResponse
    {
        $hu = HandlingUnit::findOrFail($id);

        if ($hu->is_sealed) {
            return $this->error('Handling unit is already sealed.', 'ALREADY_SEALED', 422);
        }

        $sealed = $this->handlingUnitService->seal($hu);

        return $this->success($sealed, 'Handling unit sealed.');
    }

    public function packingList(int $shipmentId): JsonResponse
    {
        $packingList = $this->handlingUnitService->getPackingList($shipmentId);

        return $this->success($packingList);
    }
}

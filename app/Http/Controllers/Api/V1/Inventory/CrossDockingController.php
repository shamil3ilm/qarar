<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\CrossDockingOrder;
use App\Models\Inventory\CrossDockingOrderLine;
use App\Services\Inventory\CrossDockingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrossDockingController extends Controller
{
    public function __construct(
        private readonly CrossDockingService $crossDockingService,
    ) {}

    /**
     * GET /inventory/cross-docking
     */
    public function index(Request $request): JsonResponse
    {
        $orders = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->when($request->input('warehouse_id'), fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['lines.product'])
            ->orderByDesc('planned_date')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($orders);
    }

    /**
     * POST /inventory/cross-docking
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => 'required|integer',
            'inbound_source_type'   => 'required|in:purchase_order,transfer_order,return',
            'inbound_source_id'     => 'required|integer',
            'outbound_dest_type'    => 'required|in:sales_order,transfer_order,delivery',
            'outbound_dest_id'      => 'required|integer',
            'planned_date'          => 'required|date',
            'dock_door_id'          => 'nullable|integer',
            'notes'                 => 'nullable|string',
            'lines'                 => 'required|array|min:1',
            'lines.*.product_id'    => 'required|integer',
            'lines.*.quantity'      => 'required|numeric|min:0.0001',
            'lines.*.unit_id'       => 'nullable|integer',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by']      = $request->user()->id;

        $order = $this->crossDockingService->createCrossDockingOrder($validated);

        return $this->created($order, 'Cross-docking order created.');
    }

    /**
     * GET /inventory/cross-docking/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->with(['lines.product', 'warehouse', 'creator'])
            ->findOrFail($id);

        return $this->success($order);
    }

    /**
     * PUT /inventory/cross-docking/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $order = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'planned_date'  => 'sometimes|date',
            'dock_door_id'  => 'nullable|integer',
            'notes'         => 'nullable|string',
        ]);

        $order->update($validated);

        return $this->success($order->fresh(), 'Cross-docking order updated.');
    }

    /**
     * DELETE /inventory/cross-docking/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $order = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($order->isCompleted()) {
            return $this->success(null, 'Completed orders cannot be deleted.', 422);
        }

        $order->delete();

        return $this->success(null, 'Cross-docking order deleted.');
    }

    /**
     * POST /inventory/cross-docking/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $order = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        try {
            $this->crossDockingService->startTransfer($order);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($order->fresh(), 'Cross-docking transfer started.');
    }

    /**
     * POST /inventory/cross-docking/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $order = CrossDockingOrder::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        try {
            $this->crossDockingService->complete($order);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($order->fresh(), 'Cross-docking order completed.');
    }

    /**
     * POST /inventory/cross-docking/lines/{lineId}/transfer
     */
    public function transferLine(Request $request, int $lineId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $line = CrossDockingOrderLine::whereHas(
            'crossDockingOrder',
            fn ($q) => $q->where('organization_id', $request->user()->organization_id)
        )->findOrFail($lineId);

        try {
            $this->crossDockingService->transferLine($line, (float) $validated['quantity']);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($line->fresh(), 'Line transfer recorded.');
    }

    /**
     * GET /inventory/cross-docking/opportunities
     */
    public function opportunities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
        ]);

        $opportunities = $this->crossDockingService->identifyOpportunities(
            (int) $validated['warehouse_id']
        );

        return $this->success($opportunities);
    }
}

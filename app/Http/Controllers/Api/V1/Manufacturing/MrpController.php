<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\DemandForecast;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\MrpRun;
use App\Services\Manufacturing\MrpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MrpController extends Controller
{
    public function __construct(
        private MrpService $mrpService
    ) {}

    /**
     * List all MRP runs for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $runs = MrpRun::with(['runBy:id,name,email'])
            ->withCount('plannedOrders')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('run_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($runs, null);
    }

    /**
     * Show a single MRP run with its demand items and planned orders.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $run = MrpRun::with([
            'runBy:id,name,email',
            'demandItems.product:id,name,sku',
            'plannedOrders.product:id,name,sku',
        ])->find($id);

        if (!$run) {
            return $this->notFound('MRP run not found.');
        }

        return $this->success($run, 'MRP run retrieved.');
    }

    /**
     * Trigger a new MRP run.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'planning_horizon_days' => 'nullable|integer|min:1|max:365',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $run = $this->mrpService->runMrp($validated, auth()->id());

        return $this->created($run, 'MRP run completed successfully.');
    }

    /**
     * List planned orders for a specific MRP run.
     */
    public function plannedOrders(Request $request, int $id): JsonResponse
    {
        $run = MrpRun::find($id);

        if (!$run) {
            return $this->notFound('MRP run not found.');
        }

        $orders = MrpPlannedOrder::where('mrp_run_id', $id)
            ->with('product:id,name,sku')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->order_type, fn ($q, $t) => $q->where('order_type', $t))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($orders, null);
    }

    /**
     * Firm a planned order.
     */
    public function firmOrder(Request $request, int $id): JsonResponse
    {
        $order = MrpPlannedOrder::find($id);

        if (!$order) {
            return $this->notFound('Planned order not found.');
        }

        try {
            $order = $this->mrpService->firmPlannedOrder($order, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($order->load('product:id,name,sku'), 'Planned order firmed.');
    }

    /**
     * Convert a planned order to a purchase order or work order.
     */
    public function convertOrder(Request $request, int $id): JsonResponse
    {
        $order = MrpPlannedOrder::find($id);

        if (!$order) {
            return $this->notFound('Planned order not found.');
        }

        try {
            $converted = $this->mrpService->convertToOrder($order, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'planned_order'  => $order->fresh(),
            'converted_to'   => $converted,
            'converted_type' => $order->converted_to_type,
        ], 'Planned order converted successfully.');
    }

    /**
     * List demand forecasts.
     */
    public function forecasts(Request $request): JsonResponse
    {
        $forecasts = DemandForecast::with(['product:id,name,sku', 'warehouse:id,name'])
            ->when($request->product_id, fn ($q, $id) => $q->forProduct((int) $id))
            ->when(
                $request->from && $request->to,
                fn ($q) => $q->forPeriod($request->from, $request->to)
            )
            ->orderByDesc('forecast_date')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($forecasts, null);
    }

    /**
     * Create or update a demand forecast.
     */
    public function storeForecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'        => 'required|exists:products,id',
            'warehouse_id'      => 'nullable|exists:warehouses,id',
            'forecast_date'     => 'required|date',
            'forecast_quantity' => 'required|numeric|min:0',
            'actual_quantity'   => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string|max:500',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $forecast = $this->mrpService->setForecast($validated, auth()->id());

        return $this->created(
            $forecast->load(['product:id,name,sku', 'warehouse:id,name']),
            'Demand forecast saved.'
        );
    }

    /**
     * Update a demand forecast.
     */
    public function updateForecast(Request $request, int $id): JsonResponse
    {
        $forecast = DemandForecast::find($id);

        if (!$forecast) {
            return $this->notFound('Demand forecast not found.');
        }

        $validated = $request->validate([
            'forecast_quantity' => 'sometimes|numeric|min:0',
            'actual_quantity'   => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string|max:500',
            'warehouse_id'      => 'nullable|exists:inventory_warehouses,id',
        ]);

        $forecast->update($validated);

        return $this->success(
            $forecast->fresh(['product:id,name,sku', 'warehouse:id,name']),
            'Demand forecast updated.'
        );
    }

    /**
     * Delete a demand forecast.
     */
    public function destroyForecast(int $id): JsonResponse
    {
        $forecast = DemandForecast::find($id);

        if (!$forecast) {
            return $this->notFound('Demand forecast not found.');
        }

        $forecast->delete();

        return $this->success(null, 'Demand forecast deleted.');
    }

    /**
     * Get MRP exceptions.
     */
    public function exceptions(Request $request): JsonResponse
    {
        $exceptions = $this->mrpService->getMrpExceptions($this->organizationId($request));

        return $this->success($exceptions, 'MRP exceptions retrieved.');
    }

    /**
     * Get forecast accuracy statistics.
     */
    public function forecastAccuracy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $accuracy = $this->mrpService->getForecastAccuracy(
            $this->organizationId($request),
            $validated['from'],
            $validated['to']
        );

        return $this->success($accuracy, 'Forecast accuracy retrieved.');
    }

    /**
     * POST mrp/capacity-check
     *
     * Run a Capacity Requirements Planning (CRP) check for all planned or
     * firmed orders within a given date range.  Persists MrpCapacityRequirement
     * records and returns a structured summary of overloaded work centers.
     */
    public function capacityCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'mrp_run_id' => 'nullable|integer|min:1',
        ]);

        $orgId   = (int) $this->organizationId($request);
        $horizon = Carbon::parse($validated['to_date']);

        $query = MrpPlannedOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [MrpPlannedOrder::STATUS_PLANNED, MrpPlannedOrder::STATUS_FIRMED])
            ->whereDate('planned_start_date', '>=', $validated['from_date'])
            ->whereDate('planned_start_date', '<=', $validated['to_date']);

        if (!empty($validated['mrp_run_id'])) {
            $query->where('mrp_run_id', $validated['mrp_run_id']);
        }

        $orders = $query->with('product:id,name,sku')->get();

        if ($orders->isEmpty()) {
            return $this->success(
                ['requirements' => [], 'overloaded_work_centers' => [], 'feasible' => true],
                'No planned orders found in the specified date range.'
            );
        }

        try {
            $result = $this->mrpService->runCapacityCheck($orders, $horizon);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'CRP_ERROR', 422);
        }

        return $this->success($result, 'Capacity requirements planning completed.');
    }

    /**
     * GET mrp/capacity-load
     *
     * Returns aggregated capacity load per work center per ISO week for a
     * given date range — suitable for rendering a load chart in the UI.
     */
    public function capacityLoad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $orgId    = (int) $this->organizationId($request);
        $fromDate = Carbon::parse($validated['from_date']);
        $toDate   = Carbon::parse($validated['to_date']);

        $load = $this->mrpService->getCapacityLoad($orgId, $fromDate, $toDate);

        return $this->success($load, 'Capacity load retrieved.');
    }

    /**
     * Convert purchase-type planned orders from an MRP run into a Purchase
     * Requisition.
     *
     * Optionally pass `planned_order_ids` to restrict which orders are
     * converted; omit the field to convert all eligible orders.
     */
    public function convertToPR(Request $request, MrpRun $mrpRun): JsonResponse
    {
        $validated = $request->validate([
            'planned_order_ids'   => 'nullable|array',
            'planned_order_ids.*' => 'integer|min:1',
        ]);

        try {
            $result = $this->mrpService->convertPlannedOrdersToPR(
                $mrpRun,
                $validated['planned_order_ids'] ?? null,
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($result, 'Planned orders converted to purchase requisition.');
    }
}

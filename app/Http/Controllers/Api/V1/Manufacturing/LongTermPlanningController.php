<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\LtpPlannedOrder;
use App\Models\Manufacturing\LtpSimulation;
use App\Services\Manufacturing\LongTermPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermPlanningController extends Controller
{
    public function __construct(
        private readonly LongTermPlanningService $service,
    ) {}

    /**
     * List LTP simulations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LtpSimulation::with(['createdBy', 'mrpRun'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderByDesc('created_at');

        $simulations = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($simulations);
    }

    /**
     * Create a new LTP simulation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'planning_horizon_from' => 'required|date',
            'planning_horizon_to'   => 'required|date|after:planning_horizon_from',
            'mrp_run_id'            => 'nullable|exists:mrp_runs,id',
        ]);

        $simulation = $this->service->create($validated);

        return $this->created($simulation);
    }

    /**
     * Show a simulation.
     */
    public function show(int $id): JsonResponse
    {
        $simulation = LtpSimulation::with(['createdBy', 'mrpRun'])
            ->withCount(['plannedOrders', 'capacityRequirements'])
            ->find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        return $this->success($simulation);
    }

    /**
     * Update a simulation (only draft simulations).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        if (!$simulation->isDraft()) {
            return $this->error('Only draft simulations can be updated.', 'INVALID_STATUS', 422, []);
        }

        $validated = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'description'           => 'nullable|string',
            'planning_horizon_from' => 'sometimes|date',
            'planning_horizon_to'   => 'sometimes|date',
            'mrp_run_id'            => 'nullable|exists:mrp_runs,id',
        ]);

        $simulation->update($validated);

        return $this->success($simulation->fresh(), 'Simulation updated.');
    }

    /**
     * Delete a simulation.
     */
    public function destroy(int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $simulation->delete();

        return $this->success(null, 'Simulation deleted.');
    }

    /**
     * Run the simulation (generate LTP planned orders and capacity requirements).
     */
    public function run(int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $this->service->runSimulation($simulation);

        return $this->success($simulation->fresh(), 'Simulation run completed.');
    }

    /**
     * Get capacity requirements overview for a simulation.
     */
    public function capacity(int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $capacity = $this->service->getCapacityOverview($id);

        return $this->success($capacity);
    }

    /**
     * Get the LTP planned orders for a simulation.
     */
    public function plannedOrders(Request $request, int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $query = LtpPlannedOrder::where('ltp_simulation_id', $id)
            ->with(['product', 'unit', 'productionVersion', 'vendor'])
            ->when($request->product_id, fn($q, $v) => $q->where('product_id', $v))
            ->when($request->order_type, fn($q, $v) => $q->where('planned_order_type', $v))
            ->orderBy('planned_start');

        $orders = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($orders);
    }

    /**
     * Compare the simulation against the operative MRP plan.
     */
    public function compare(int $id): JsonResponse
    {
        $simulation = LtpSimulation::find($id);

        if ($simulation === null) {
            return $this->notFound('Simulation not found.');
        }

        $comparison = $this->service->compareWithOperativePlan($id);

        return $this->success($comparison);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\LtpCapacityRequirement;
use App\Models\Manufacturing\LtpPlannedOrder;
use App\Models\Manufacturing\LtpSimulation;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\WorkCenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LongTermPlanningService
{
    /**
     * Create a new LTP simulation record.
     */
    public function create(array $data): LtpSimulation
    {
        return LtpSimulation::create([
            'organization_id'       => auth()->user()->organization_id,
            'name'                  => $data['name'],
            'description'           => $data['description'] ?? null,
            'planning_horizon_from' => $data['planning_horizon_from'],
            'planning_horizon_to'   => $data['planning_horizon_to'],
            'status'                => LtpSimulation::STATUS_DRAFT,
            'mrp_run_id'            => $data['mrp_run_id'] ?? null,
            'created_by'            => auth()->id(),
        ]);
    }

    /**
     * Run the simulation: generate LTP planned orders by copying/projecting
     * from the operative MRP planned orders within the horizon.
     */
    public function runSimulation(LtpSimulation $simulation): void
    {
        if (!$simulation->canBeRun()) {
            throw new \LogicException("Simulation '{$simulation->name}' cannot be run in status '{$simulation->status}'.");
        }

        DB::transaction(function () use ($simulation): void {
            $simulation->update(['status' => LtpSimulation::STATUS_RUNNING]);

            // Clear previous simulation data
            $simulation->plannedOrders()->delete();
            $simulation->capacityRequirements()->delete();

            // Copy operative MRP planned orders that fall within the horizon
            $mrpOrders = MrpPlannedOrder::where('planned_start', '>=', $simulation->planning_horizon_from)
                ->where('planned_start', '<=', $simulation->planning_horizon_to)
                ->get();

            foreach ($mrpOrders as $mrpOrder) {
                LtpPlannedOrder::create([
                    'ltp_simulation_id'     => $simulation->id,
                    'product_id'            => $mrpOrder->product_id,
                    'planned_order_type'    => $mrpOrder->order_type ?? LtpPlannedOrder::TYPE_PRODUCTION,
                    'quantity'              => $mrpOrder->quantity,
                    'unit_id'               => $mrpOrder->unit_id ?? null,
                    'planned_start'         => $mrpOrder->planned_start,
                    'planned_finish'        => $mrpOrder->planned_finish,
                    'production_version_id' => null,
                    'vendor_id'             => null,
                ]);
            }

            $this->calculateCapacity($simulation);

            $simulation->update([
                'status' => LtpSimulation::STATUS_COMPLETED,
                'run_at' => now(),
            ]);
        });
    }

    /**
     * Calculate capacity requirements for a completed simulation.
     */
    public function calculateCapacity(LtpSimulation $simulation): void
    {
        // Clear existing capacity data for this simulation
        $simulation->capacityRequirements()->delete();

        $workCenters = WorkCenter::active()->get();

        $from = Carbon::parse($simulation->planning_horizon_from);
        $to   = Carbon::parse($simulation->planning_horizon_to);

        foreach ($workCenters as $workCenter) {
            $current = $from->copy();
            while ($current->lte($to)) {
                $availableHours = $workCenter->getAvailableHoursForDate($current->toDateTime());

                // Aggregate required hours from LTP planned orders that touch this work center on this date
                // (simplified: sum of planned order quantities divided by work center capacity as a proxy)
                $requiredHours = $this->estimateRequiredHours($simulation, $workCenter->id, $current->toDateString());

                $utilization = $availableHours > 0
                    ? round(($requiredHours / $availableHours) * 100, 2)
                    : 0.0;

                LtpCapacityRequirement::create([
                    'ltp_simulation_id'      => $simulation->id,
                    'work_center_id'         => $workCenter->id,
                    'calendar_date'          => $current->toDateString(),
                    'required_hours'         => $requiredHours,
                    'available_hours'        => $availableHours,
                    'utilization_percentage' => $utilization,
                ]);

                $current->addDay();
            }
        }
    }

    /**
     * Compare LTP simulation planned orders against the operative MRP planned orders.
     *
     * @return array{
     *   simulation_id: int,
     *   ltp_total_orders: int,
     *   operative_total_orders: int,
     *   ltp_only: list<array<string, mixed>>,
     *   operative_only: list<array<string, mixed>>,
     *   matched: list<array<string, mixed>>
     * }
     */
    public function compareWithOperativePlan(int $simulationId): array
    {
        $simulation = LtpSimulation::with('plannedOrders.product')->findOrFail($simulationId);

        $operativeOrders = MrpPlannedOrder::where('planned_start', '>=', $simulation->planning_horizon_from)
            ->where('planned_start', '<=', $simulation->planning_horizon_to)
            ->with('product')
            ->get();

        $ltpByProduct      = $simulation->plannedOrders->groupBy('product_id');
        $operativeByProduct = $operativeOrders->groupBy('product_id');

        $ltpOnly       = [];
        $operativeOnly = [];
        $matched       = [];

        foreach ($ltpByProduct as $productId => $ltpOrders) {
            if ($operativeByProduct->has($productId)) {
                $matched[] = [
                    'product_id'       => $productId,
                    'ltp_quantity'      => $ltpOrders->sum('quantity'),
                    'operative_quantity' => $operativeByProduct[$productId]->sum('quantity'),
                ];
            } else {
                $ltpOnly[] = [
                    'product_id' => $productId,
                    'quantity'   => $ltpOrders->sum('quantity'),
                ];
            }
        }

        foreach ($operativeByProduct as $productId => $opOrders) {
            if (!$ltpByProduct->has($productId)) {
                $operativeOnly[] = [
                    'product_id' => $productId,
                    'quantity'   => $opOrders->sum('quantity'),
                ];
            }
        }

        return [
            'simulation_id'          => $simulationId,
            'ltp_total_orders'       => $simulation->plannedOrders->count(),
            'operative_total_orders' => $operativeOrders->count(),
            'ltp_only'               => $ltpOnly,
            'operative_only'         => $operativeOnly,
            'matched'                => $matched,
        ];
    }

    /**
     * Get all capacity requirements grouped by work center for a simulation.
     */
    public function getCapacityOverview(int $simulationId): Collection
    {
        return LtpCapacityRequirement::where('ltp_simulation_id', $simulationId)
            ->with('workCenter')
            ->orderBy('work_center_id')
            ->orderBy('calendar_date')
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Estimate required hours on a given date for a work center within the simulation.
     * This is a simplified estimation; production scheduling would need routing data.
     */
    private function estimateRequiredHours(LtpSimulation $simulation, int $workCenterId, string $date): float
    {
        // For now, count planned production orders starting on this date
        $count = LtpPlannedOrder::where('ltp_simulation_id', $simulation->id)
            ->where('planned_start', $date)
            ->where('planned_order_type', LtpPlannedOrder::TYPE_PRODUCTION)
            ->count();

        // Rough estimate: each production order requires 1 hour by default
        return (float) $count;
    }
}

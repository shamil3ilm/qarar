<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Inventory\StockLevel;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceOrderTask;
use App\Models\Maintenance\MaintenancePlan;
use App\Services\Inventory\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintenanceService
{
    public function __construct(private readonly StockService $stockService) {}

    // -------------------------------------------------------------------------
    // Equipment
    // -------------------------------------------------------------------------

    /**
     * Create a new equipment record.
     */
    public function createEquipment(array $data, int $userId): Equipment
    {
        return DB::transaction(function () use ($data, $userId): Equipment {
            return Equipment::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    /**
     * Update an equipment record.
     */
    public function updateEquipment(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);
        return $equipment->fresh();
    }

    // -------------------------------------------------------------------------
    // Maintenance Plans
    // -------------------------------------------------------------------------

    /**
     * Create a new maintenance plan.
     */
    public function createMaintenancePlan(array $data, int $userId): MaintenancePlan
    {
        return DB::transaction(function () use ($data, $userId): MaintenancePlan {
            return MaintenancePlan::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    /**
     * Update an existing maintenance plan.
     */
    public function updateMaintenancePlan(MaintenancePlan $plan, array $data): MaintenancePlan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    /**
     * Generate a maintenance order from a plan and update tracking timestamps.
     */
    public function generateOrderFromPlan(MaintenancePlan $plan, int $userId): MaintenanceOrder
    {
        if (!$plan->is_active) {
            throw new \InvalidArgumentException('Cannot generate an order from an inactive maintenance plan.');
        }

        return DB::transaction(function () use ($plan, $userId): MaintenanceOrder {
            $orgId = $plan->organization_id;

            $order = MaintenanceOrder::create([
                'organization_id'    => $orgId,
                'order_number'       => MaintenanceOrder::generateOrderNumber($orgId),
                'maintenance_plan_id' => $plan->id,
                'equipment_id'       => $plan->equipment_id,
                'order_type'         => MaintenanceOrder::TYPE_PREVENTIVE,
                'priority'           => MaintenanceOrder::PRIORITY_MEDIUM,
                'status'             => MaintenanceOrder::STATUS_OPEN,
                'description'        => $plan->description ?? $plan->name,
                'estimated_cost'     => null,
                'created_by'         => $userId,
            ]);

            // Create tasks from the plan's task list
            if (!empty($plan->tasks)) {
                foreach ($plan->tasks as $index => $taskData) {
                    $order->tasks()->create([
                        'task_description'  => is_array($taskData) ? ($taskData['description'] ?? $taskData) : $taskData,
                        'is_safety_critical' => is_array($taskData) ? (bool) ($taskData['is_safety_critical'] ?? false) : false,
                        'sort_order'        => $index,
                    ]);
                }
            }

            // Update plan tracking
            $plan->update(['last_generated_at' => now()]);

            // Update equipment next maintenance date
            $nextDue = $plan->calculateNextDueDate(new \DateTime(now()->toDateString()));
            $plan->equipment->update(['next_maintenance_date' => $nextDue->format('Y-m-d')]);

            return $order->load(['tasks', 'parts', 'equipment']);
        });
    }

    // -------------------------------------------------------------------------
    // Maintenance Orders
    // -------------------------------------------------------------------------

    /**
     * Create a maintenance order with optional tasks and parts.
     *
     * @param  array  $tasks  Array of task definition arrays with keys:
     *                        task_description, is_safety_critical, sort_order, notes
     * @param  array  $parts  Array of part line arrays with keys:
     *                        product_id, description, quantity_required, unit_cost
     */
    public function createMaintenanceOrder(
        array $data,
        array $tasks,
        array $parts,
        int $userId
    ): MaintenanceOrder {
        return DB::transaction(function () use ($data, $tasks, $parts, $userId): MaintenanceOrder {
            $orgId = $data['organization_id'] ?? auth()->user()->organization_id;

            $order = MaintenanceOrder::create(array_merge($data, [
                'organization_id' => $orgId,
                'order_number'    => MaintenanceOrder::generateOrderNumber($orgId),
                'created_by'      => $userId,
                'status'          => $data['status'] ?? MaintenanceOrder::STATUS_OPEN,
            ]));

            foreach ($tasks as $index => $taskData) {
                $order->tasks()->create(array_merge($taskData, [
                    'sort_order' => $taskData['sort_order'] ?? $index,
                ]));
            }

            foreach ($parts as $partData) {
                $order->parts()->create($partData);
            }

            return $order->load(['tasks', 'parts', 'equipment', 'plan']);
        });
    }

    /**
     * Start a maintenance order (transition to in_progress).
     */
    public function startOrder(MaintenanceOrder $order, int $userId): MaintenanceOrder
    {
        return DB::transaction(function () use ($order, $userId): MaintenanceOrder {
            return $order->start($userId);
        });
    }

    /**
     * Mark a single task as complete.
     */
    public function completeTask(
        MaintenanceOrder $order,
        int $taskId,
        string $notes,
        int $userId
    ): MaintenanceOrderTask {
        $task = $order->tasks()->findOrFail($taskId);

        if ($task->is_completed) {
            throw new \InvalidArgumentException('Task is already completed.');
        }

        $task->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId,
            'notes'        => $notes,
        ]);

        return $task->fresh();
    }

    /**
     * Complete a maintenance order.
     *
     * SAP PM equivalent: order completion triggers movement type 261
     * (goods issue to maintenance order) for each part with quantity_used > 0.
     */
    public function completeOrder(MaintenanceOrder $order, array $data, int $userId): MaintenanceOrder
    {
        $completed = DB::transaction(function () use ($order, $data, $userId): MaintenanceOrder {
            return $order->complete($data, $userId);
        });

        // Post PM goods movements outside the completion transaction so a stock
        // failure never rolls back the maintenance record itself.
        $this->issueMaterialsToOrder($completed);

        return $completed;
    }

    /**
     * Issue parts consumed by a completed maintenance order to stock (movement type 261).
     */
    private function issueMaterialsToOrder(MaintenanceOrder $order): void
    {
        $parts = $order->parts()
            ->whereNotNull('product_id')
            ->where('quantity_used', '>', 0)
            ->get();

        if ($parts->isEmpty()) {
            return;
        }

        foreach ($parts as $part) {
            // Find the warehouse holding the most available stock for this product.
            $stockLevel = StockLevel::where('organization_id', $order->organization_id)
                ->where('product_id', $part->product_id)
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->first();

            if ($stockLevel === null) {
                Log::warning('PM goods movement skipped — no stock found', [
                    'order_id'   => $order->id,
                    'product_id' => $part->product_id,
                ]);
                continue;
            }

            try {
                $this->stockService->recordMovement(
                    productId:     $part->product_id,
                    warehouseId:   $stockLevel->warehouse_id,
                    movementType:  'maintenance_issue', // movement type 261
                    direction:     'OUT',
                    quantity:      (float) $part->quantity_used,
                    unitCost:      (float) ($part->unit_cost ?? $stockLevel->average_cost ?? 0),
                    referenceType: 'maintenance_order',
                    referenceId:   $order->id,
                );
            } catch (\Throwable $e) {
                Log::warning('PM goods movement failed', [
                    'order_id'   => $order->id,
                    'product_id' => $part->product_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Cancel a maintenance order.
     */
    public function cancelOrder(MaintenanceOrder $order, int $userId): MaintenanceOrder
    {
        return DB::transaction(function () use ($order, $userId): MaintenanceOrder {
            return $order->cancel($userId);
        });
    }

    // -------------------------------------------------------------------------
    // Reporting helpers
    // -------------------------------------------------------------------------

    /**
     * Return equipment whose next maintenance date falls within the next $days days.
     */
    public function getDueEquipment(int $orgId, int $days = 7): Collection
    {
        return Equipment::forOrganization($orgId)
            ->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', now()->addDays($days)->toDateString())
            ->with(['category', 'functionalLocation'])
            ->orderBy('next_maintenance_date')
            ->get();
    }

    /**
     * Aggregate maintenance statistics for a date range.
     *
     * Returns:
     *   total_orders, by_type[], by_status[], mttr_hours (mean time to repair), total_downtime_hours
     */
    public function getMaintenanceStats(int $orgId, string $from, string $to): array
    {
        $base = MaintenanceOrder::forOrganization($orgId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        $total = (clone $base)->count();

        $byType = (clone $base)
            ->selectRaw('order_type, COUNT(*) as count')
            ->groupBy('order_type')
            ->pluck('count', 'order_type')
            ->toArray();

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Mean time to repair: average minutes between actual_start and actual_end for completed orders
        $completedOrders = (clone $base)
            ->where('status', MaintenanceOrder::STATUS_COMPLETED)
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_end')
            ->selectRaw('TIMESTAMPDIFF(MINUTE, actual_start, actual_end) as repair_minutes, downtime_hours')
            ->get();

        $mttrHours = 0.0;
        $totalDowntime = 0.0;

        if ($completedOrders->isNotEmpty()) {
            $avgMinutes = $completedOrders->avg('repair_minutes');
            $mttrHours  = round($avgMinutes / 60, 2);
            $totalDowntime = round((float) $completedOrders->sum('downtime_hours'), 2);
        }

        return [
            'total_orders'        => $total,
            'by_type'             => $byType,
            'by_status'           => $byStatus,
            'mttr_hours'          => $mttrHours,
            'total_downtime_hours' => $totalDowntime,
        ];
    }
}

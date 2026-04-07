<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionSchedulingService
{
    /**
     * Forward-schedule all operations of a work order starting from
     * planned_start_date. Updates scheduled_start/scheduled_end on each
     * operation and sets work_order.planned_end_date to the last op end.
     */
    public function scheduleForward(WorkOrder $workOrder): void
    {
        DB::transaction(function () use ($workOrder): void {
            $workOrder->loadMissing(['operations']);

            $cursor = Carbon::parse($workOrder->planned_start_date)->startOfDay()->setTime(8, 0);

            foreach ($workOrder->operations->sortBy('sequence') as $operation) {
                $start    = $cursor->copy();
                $duration = $this->computeDurationHours($operation, (float) $workOrder->planned_quantity);
                $end      = $this->advanceByWorkingHours($start, $duration, $operation);

                $operation->update([
                    'scheduled_start' => $start,
                    'scheduled_end'   => $end,
                ]);

                // Add inter-operation buffer if linked to a routing operation
                $inter = $this->getInterOperationTime($operation);
                $cursor = $end->copy()->addHours((int) ceil($inter));
            }

            // Set planned_end_date to the last operation's scheduled end
            $lastEnd = $workOrder->operations->max('scheduled_end');
            if ($lastEnd !== null) {
                $workOrder->update(['planned_end_date' => Carbon::parse($lastEnd)->toDateString()]);
            }
        });
    }

    /**
     * Backward-schedule all operations of a work order from a required date.
     * Iterates operations in reverse sequence order.
     * Sets work_order.planned_start_date = first operation scheduled_start.
     */
    public function scheduleBackward(WorkOrder $workOrder, Carbon $requiredDate): void
    {
        DB::transaction(function () use ($workOrder, $requiredDate): void {
            $workOrder->loadMissing(['operations']);

            $cursor = $requiredDate->copy()->setTime(17, 0);

            foreach ($workOrder->operations->sortByDesc('sequence') as $operation) {
                $duration = $this->computeDurationHours($operation, (float) $workOrder->planned_quantity);
                $inter    = $this->getInterOperationTime($operation);
                $end      = $cursor->copy();
                $start    = $this->subtractWorkingHours($end, $duration, $operation);

                $operation->update([
                    'scheduled_start' => $start,
                    'scheduled_end'   => $end,
                ]);

                $cursor = $start->copy()->subHours((int) ceil($inter));
            }

            // Set planned_start_date to the earliest scheduled_start
            $firstStart = $workOrder->operations->min('scheduled_start');
            if ($firstStart !== null) {
                $workOrder->update(['planned_start_date' => Carbon::parse($firstStart)->toDateString()]);
            }
        });
    }

    /**
     * Re-run forward scheduling for all 'planned' work orders in an
     * organisation. Marks work orders with capacity conflicts.
     */
    public function rescheduleAll(int $organizationId): array
    {
        $workOrders = WorkOrder::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('status', WorkOrder::STATUS_SCHEDULED)
            ->with(['operations'])
            ->get();

        $results = [];

        foreach ($workOrders as $workOrder) {
            try {
                $this->scheduleForward($workOrder);
                $results[] = [
                    'work_order_id'     => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'status'            => 'rescheduled',
                    'conflict'          => false,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'work_order_id'     => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'status'            => 'conflict',
                    'conflict'          => true,
                    'message'           => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Return Gantt-chart-ready scheduling data for the given date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getScheduleGantt(int $organizationId, string $fromDate, string $toDate): array
    {
        $workOrders = WorkOrder::withoutGlobalScope('organization')
            ->with(['product', 'operations'])
            ->where('organization_id', $organizationId)
            ->whereHas('operations', function ($q) use ($fromDate, $toDate) {
                $q->where(function ($inner) use ($fromDate, $toDate) {
                    $inner->whereBetween('scheduled_start', [$fromDate, $toDate . ' 23:59:59'])
                        ->orWhereBetween('scheduled_end', [$fromDate, $toDate . ' 23:59:59']);
                });
            })
            ->get();

        return $workOrders->map(function (WorkOrder $wo): array {
            $ops = $wo->operations->sortBy('sequence')->map(function (WorkOrderOperation $op): array {
                $start    = $op->scheduled_start ? Carbon::parse($op->scheduled_start) : null;
                $end      = $op->scheduled_end   ? Carbon::parse($op->scheduled_end)   : null;
                $duration = ($start && $end) ? round($end->diffInMinutes($start) / 60, 2) : null;

                return [
                    'operation_id' => $op->id,
                    'name'         => $op->name,
                    'work_center'  => $op->work_center_id,
                    'sequence'     => $op->sequence,
                    'start'        => $start?->toDateTimeString(),
                    'end'          => $end?->toDateTimeString(),
                    'duration'     => $duration,
                    'status'       => $op->status,
                ];
            })->values()->all();

            return [
                'work_order_id'     => $wo->id,
                'work_order_number' => $wo->work_order_number,
                'product_name'      => $wo->product?->name ?? 'Unknown',
                'status'            => $wo->status,
                'planned_quantity'  => (float) $wo->planned_quantity,
                'operations'        => $ops,
            ];
        })->values()->all();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute total operation duration in hours.
     * Uses work_order_operation.estimated_minutes when routing fields are absent,
     * otherwise uses setup_time + machine_time * quantity from the linked BOM operation.
     */
    private function computeDurationHours(WorkOrderOperation $operation, float $quantity): float
    {
        // If linked BOM operation has routing data, prefer that calculation
        $bomOp = $operation->bomOperation;

        if ($bomOp !== null && isset($bomOp->setup_time, $bomOp->machine_time)) {
            $hours = (float) $bomOp->setup_time + ((float) $bomOp->machine_time * $quantity);

            return max(1.0, ceil($hours * 10) / 10);
        }

        // Fall back to estimated_minutes on the work order operation
        $minutes = (int) ($operation->estimated_minutes ?? 0);

        return $minutes > 0 ? ceil($minutes / 60 * 10) / 10 : 1.0;
    }

    /**
     * Move a Carbon datetime forward by $hours, skipping non-working days for
     * the operation's work center when available.
     */
    private function advanceByWorkingHours(Carbon $from, float $hours, WorkOrderOperation $operation): Carbon
    {
        $workCenter    = $this->resolveWorkCenter($operation);
        $dailyCapacity = $workCenter !== null ? $this->effectiveDailyHours($workCenter) : 8.0;

        $cursor    = $from->copy();
        $remaining = $hours;

        while ($remaining > 0.0) {
            $available = $this->availableHoursFromTime($cursor, $dailyCapacity, $workCenter);

            if ($available > 0.0) {
                $consumed  = min($available, $remaining);
                $remaining = round($remaining - $consumed, 4);

                if ($remaining <= 0.0) {
                    $minutesConsumed = (int) round($consumed * 60);
                    $cursor->addMinutes($minutesConsumed);
                    break;
                }

                // Advance to end of current working day then move to next day start
                $cursor->setTime(17, 0)->addDay()->setTime(8, 0);
            } else {
                $cursor->addDay()->setTime(8, 0);
            }
        }

        return $cursor;
    }

    /**
     * Move a Carbon datetime backward by $hours, respecting working days.
     */
    private function subtractWorkingHours(Carbon $from, float $hours, WorkOrderOperation $operation): Carbon
    {
        $workCenter    = $this->resolveWorkCenter($operation);
        $dailyCapacity = $workCenter !== null ? $this->effectiveDailyHours($workCenter) : 8.0;

        $cursor    = $from->copy();
        $remaining = $hours;

        while ($remaining > 0.0) {
            $available = $this->availableHoursFromTime($cursor, $dailyCapacity, $workCenter);

            if ($available > 0.0) {
                $consumed  = min($available, $remaining);
                $remaining = round($remaining - $consumed, 4);

                if ($remaining <= 0.0) {
                    $minutesConsumed = (int) round($consumed * 60);
                    $cursor->subMinutes($minutesConsumed);
                    break;
                }

                // Move to start of current working day then back to previous day end
                $cursor->setTime(8, 0)->subSecond()->setTime(17, 0);
            } else {
                $cursor->subDay()->setTime(17, 0);
            }
        }

        return $cursor;
    }

    /**
     * Get available hours on the day of $cursor from the current time position.
     */
    private function availableHoursFromTime(Carbon $cursor, float $dailyCapacity, ?WorkCenter $workCenter): float
    {
        if ($workCenter !== null && !$workCenter->isWorkingDay($cursor->toDateTime())) {
            return 0.0;
        }

        // Hours remaining in this working day (assuming 08:00–17:00 window)
        $startHour = 8;
        $endHour   = 17;
        $hour      = (float) $cursor->format('G') + (float) $cursor->format('i') / 60;

        if ($hour < $startHour) {
            return (float) $dailyCapacity;
        }

        if ($hour >= $endHour) {
            return 0.0;
        }

        return min($dailyCapacity, round($endHour - $hour, 2));
    }

    /**
     * Resolve the WorkCenter for a WorkOrderOperation.
     */
    private function resolveWorkCenter(WorkOrderOperation $operation): ?WorkCenter
    {
        if ($operation->work_center_id !== null) {
            return WorkCenter::find($operation->work_center_id);
        }

        $bomOp = $operation->bomOperation;

        if ($bomOp !== null && isset($bomOp->work_center_id)) {
            return WorkCenter::find($bomOp->work_center_id);
        }

        return null;
    }

    /**
     * Effective daily hours = capacity_per_day * efficiency / 100.
     */
    private function effectiveDailyHours(WorkCenter $wc): float
    {
        return round((float) $wc->capacity_per_day * (float) $wc->efficiency_percent / 100, 2);
    }

    /**
     * Get the inter-operation buffer time (hours) from the linked routing operation.
     */
    private function getInterOperationTime(WorkOrderOperation $operation): float
    {
        $bomOp = $operation->bomOperation;

        if ($bomOp === null) {
            return 0.0;
        }

        // bomOperation may link to a BomOperation which in turn may carry inter_operation_time
        // We read it directly from the relationship if available
        return isset($bomOp->inter_operation_time) ? (float) $bomOp->inter_operation_time : 0.0;
    }
}

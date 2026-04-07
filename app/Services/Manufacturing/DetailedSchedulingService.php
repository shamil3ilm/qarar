<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\SchedulingBoard;
use App\Models\Manufacturing\SchedulingOperation;
use App\Models\Manufacturing\SchedulingPeggingRelationship;
use App\Models\Manufacturing\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DetailedSchedulingService
{
    /**
     * Return Gantt-ready data for a scheduling board in the given window.
     *
     * @return array{
     *   board: array<string, mixed>,
     *   work_centers: list<array<string, mixed>>,
     *   operations: list<array<string, mixed>>
     * }
     */
    public function getBoardData(int $boardId, string $dateFrom, string $dateTo): array
    {
        $board = SchedulingBoard::with('operations.workCenter')->findOrFail($boardId);

        $operations = SchedulingOperation::forBoard($boardId)
            ->with(['workCenter', 'workOrder', 'processOrder', 'predecessorRelationships', 'successorRelationships'])
            ->where('planned_start', '>=', $dateFrom)
            ->where('planned_finish', '<=', $dateTo . ' 23:59:59')
            ->orderBy('work_center_id')
            ->orderBy('planned_start')
            ->get();

        $workCenters = $operations->pluck('workCenter')->unique('id')->values();

        return [
            'board'        => [
                'id'           => $board->id,
                'name'         => $board->name,
                'horizon_days' => $board->horizon_days,
            ],
            'work_centers' => $workCenters->map(fn($wc) => [
                'id'   => $wc->id,
                'code' => $wc->code,
                'name' => $wc->name,
            ])->all(),
            'operations'   => $operations->map(fn($op) => $this->serializeOperation($op))->all(),
        ];
    }

    /**
     * Move an operation to a new start time and cascade the shift to successors.
     */
    public function rescheduleOperation(SchedulingOperation $op, string $newStart): void
    {
        if ($op->is_fixed) {
            throw new \LogicException("Operation {$op->id} is fixed and cannot be rescheduled.");
        }

        DB::transaction(function () use ($op, $newStart): void {
            $oldStart = $op->planned_start;
            $newStartCarbon = Carbon::parse($newStart);
            $deltaMinutes = $oldStart->diffInMinutes($newStartCarbon, false);

            $newFinish = Carbon::parse($op->planned_finish)->addMinutes($deltaMinutes);

            $op->update([
                'planned_start'  => $newStartCarbon,
                'planned_finish' => $newFinish,
            ]);

            $this->cascadeSuccessors($op, $deltaMinutes);
        });
    }

    /**
     * Suggest an optimised sequence for a work center on a given date.
     * Uses shortest processing time (SPT) heuristic.
     *
     * @return list<array<string, mixed>>
     */
    public function optimizeSequence(int $workCenterId, string $date): array
    {
        $operations = SchedulingOperation::forWorkCenter($workCenterId)
            ->whereDate('planned_start', $date)
            ->where('is_fixed', false)
            ->orderBy('duration_minutes')
            ->get();

        return $operations->values()->map(fn($op, $idx) => [
            'operation_id'     => $op->id,
            'suggested_sequence' => $idx + 1,
            'description'      => $op->description,
            'duration_minutes' => $op->duration_minutes,
            'current_start'    => $op->planned_start->toIso8601String(),
        ])->all();
    }

    /**
     * Create scheduling operations from all operations on a work order.
     */
    public function createFromWorkOrder(int $workOrderId, ?int $boardId = null): Collection
    {
        $workOrder = WorkOrder::with('operations.workCenter')->findOrFail($workOrderId);

        $created = new Collection();

        DB::transaction(function () use ($workOrder, $boardId, &$created): void {
            foreach ($workOrder->operations as $op) {
                $workCenterId = $op->work_center_id ?? null;
                if ($workCenterId === null) {
                    continue;
                }

                $durationMinutes = (int) (($op->estimated_minutes ?? 60));
                $plannedStart    = $workOrder->planned_start_date
                    ? Carbon::parse($workOrder->planned_start_date)
                    : now();
                $plannedFinish   = $plannedStart->copy()->addMinutes($durationMinutes);

                $schedOp = SchedulingOperation::create([
                    'organization_id'     => auth()->user()->organization_id,
                    'scheduling_board_id' => $boardId,
                    'work_order_id'       => $workOrder->id,
                    'work_center_id'      => $workCenterId,
                    'operation_number'    => $op->sequence ?? 1,
                    'description'         => $op->operation_name ?? $op->description ?? 'Operation',
                    'planned_start'       => $plannedStart,
                    'planned_finish'      => $plannedFinish,
                    'duration_minutes'    => $durationMinutes,
                    'setup_minutes'       => 0,
                    'teardown_minutes'    => 0,
                    'priority'            => 50,
                ]);

                $created->push($schedOp);
            }
        });

        return $created;
    }

    /**
     * Detect scheduling conflicts (overlapping operations) on a work center.
     *
     * @return list<array{operation_a: int, operation_b: int, overlap_minutes: int}>
     */
    public function detectConflicts(int $workCenterId, string $dateFrom, string $dateTo): array
    {
        $operations = SchedulingOperation::forWorkCenter($workCenterId)
            ->where('planned_start', '>=', $dateFrom)
            ->where('planned_finish', '<=', $dateTo . ' 23:59:59')
            ->orderBy('planned_start')
            ->get();

        $conflicts = [];

        for ($i = 0; $i < $operations->count(); $i++) {
            for ($j = $i + 1; $j < $operations->count(); $j++) {
                $a = $operations[$i];
                $b = $operations[$j];

                if ($b->planned_start->lt($a->planned_finish)) {
                    $overlapMinutes = (int) $b->planned_start->diffInMinutes(
                        min($a->planned_finish, $b->planned_finish)
                    );

                    $conflicts[] = [
                        'operation_a'    => $a->id,
                        'operation_b'    => $b->id,
                        'overlap_minutes' => $overlapMinutes,
                    ];
                }
            }
        }

        return $conflicts;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Recursively cascade a time shift to successor operations.
     */
    private function cascadeSuccessors(SchedulingOperation $op, int $deltaMinutes): void
    {
        foreach ($op->predecessorRelationships as $rel) {
            $successor = SchedulingOperation::find($rel->successor_operation_id);

            if ($successor === null || $successor->is_fixed || $successor->is_pinned) {
                continue;
            }

            $newStart  = Carbon::parse($successor->planned_start)->addMinutes($deltaMinutes);
            $newFinish = Carbon::parse($successor->planned_finish)->addMinutes($deltaMinutes);

            $successor->update([
                'planned_start'  => $newStart,
                'planned_finish' => $newFinish,
            ]);

            $this->cascadeSuccessors($successor, $deltaMinutes);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOperation(SchedulingOperation $op): array
    {
        return [
            'id'               => $op->id,
            'work_center_id'   => $op->work_center_id,
            'work_order_id'    => $op->work_order_id,
            'process_order_id' => $op->process_order_id,
            'operation_number' => $op->operation_number,
            'description'      => $op->description,
            'planned_start'    => $op->planned_start->toIso8601String(),
            'planned_finish'   => $op->planned_finish->toIso8601String(),
            'actual_start'     => $op->actual_start?->toIso8601String(),
            'actual_finish'    => $op->actual_finish?->toIso8601String(),
            'duration_minutes' => $op->duration_minutes,
            'setup_minutes'    => $op->setup_minutes,
            'teardown_minutes' => $op->teardown_minutes,
            'priority'         => $op->priority,
            'is_pinned'        => $op->is_pinned,
            'is_fixed'         => $op->is_fixed,
            'sequence_number'  => $op->sequence_number,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\ProductionSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionSchedulingController extends Controller
{
    public function __construct(
        private ProductionSchedulingService $schedulingService
    ) {}

    /**
     * POST manufacturing/work-orders/{workOrder}/schedule/forward
     *
     * Forward-schedule a work order starting from its planned_start_date.
     */
    public function scheduleForward(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->schedulingService->scheduleForward($workOrder);
            $workOrder->refresh()->loadMissing(['operations', 'product']);

            return $this->success(
                $this->buildWorkOrderSchedule($workOrder),
                'Work order forward-scheduled successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error('SCHEDULE_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * POST manufacturing/work-orders/{workOrder}/schedule/backward
     *
     * Backward-schedule a work order from a required date.
     * Body: { "required_date": "YYYY-MM-DD" }
     */
    public function scheduleBackward(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'required_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            $requiredDate = Carbon::parse($validated['required_date']);
            $this->schedulingService->scheduleBackward($workOrder, $requiredDate);
            $workOrder->refresh()->loadMissing(['operations', 'product']);

            return $this->success(
                $this->buildWorkOrderSchedule($workOrder),
                'Work order backward-scheduled successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error('SCHEDULE_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * POST manufacturing/schedule/reschedule-all
     *
     * Re-run forward scheduling for all scheduled work orders in the organisation.
     */
    public function rescheduleAll(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('NO_ORGANIZATION', 'Organization context required.', 422);
        }

        $results = $this->schedulingService->rescheduleAll($orgId);

        $rescheduled = collect($results)->where('status', 'rescheduled')->count();
        $conflicts   = collect($results)->where('conflict', true)->count();

        return $this->success(
            [
                'results'           => $results,
                'total'             => count($results),
                'rescheduled_count' => $rescheduled,
                'conflict_count'    => $conflicts,
            ],
            "Reschedule complete: {$rescheduled} rescheduled, {$conflicts} conflicts."
        );
    }

    /**
     * GET manufacturing/schedule/gantt?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Return Gantt-chart-ready data for work orders in the date range.
     */
    public function gantt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('NO_ORGANIZATION', 'Organization context required.', 422);
        }

        $gantt = $this->schedulingService->getScheduleGantt(
            $orgId,
            $validated['from'],
            $validated['to']
        );

        return $this->success([
            'from'        => $validated['from'],
            'to'          => $validated['to'],
            'work_orders' => $gantt,
            'count'       => count($gantt),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a summary array for a work order with its operation schedule.
     *
     * @return array<string, mixed>
     */
    private function buildWorkOrderSchedule(WorkOrder $workOrder): array
    {
        return [
            'work_order_id'      => $workOrder->id,
            'work_order_number'  => $workOrder->work_order_number,
            'product_name'       => $workOrder->product?->name,
            'planned_start_date' => $workOrder->planned_start_date?->toDateString(),
            'planned_end_date'   => $workOrder->planned_end_date?->toDateString(),
            'status'             => $workOrder->status,
            'operations'         => $workOrder->operations->sortBy('sequence')->map(fn($op) => [
                'id'              => $op->id,
                'name'            => $op->name,
                'sequence'        => $op->sequence,
                'scheduled_start' => $op->scheduled_start?->toDateTimeString(),
                'scheduled_end'   => $op->scheduled_end?->toDateTimeString(),
                'status'          => $op->status,
            ])->values()->all(),
        ];
    }
}

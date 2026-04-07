<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\CapacityPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapacityLevelingController extends Controller
{
    public function __construct(
        private CapacityPlanningService $capacityService
    ) {}

    /**
     * GET manufacturing/capacity-leveling/suggest?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Returns leveling suggestions without applying them.
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('NO_ORGANIZATION', 'Organization context required.', 422);
        }

        $suggestions = $this->capacityService->suggestLevelingActions(
            $orgId,
            $validated['from'],
            $validated['to']
        );

        return $this->success([
            'from'        => $validated['from'],
            'to'          => $validated['to'],
            'suggestions' => $suggestions,
            'count'       => count($suggestions),
        ]);
    }

    /**
     * POST manufacturing/capacity-leveling/apply
     *
     * Apply capacity leveling with the chosen strategy.
     * Body: { "from": "YYYY-MM-DD", "to": "YYYY-MM-DD", "strategy": "delay|split" }
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'     => 'required|date',
            'to'       => 'required|date|after_or_equal:from',
            'strategy' => 'required|in:delay,split',
        ]);

        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('NO_ORGANIZATION', 'Organization context required.', 422);
        }

        $results = $this->capacityService->levelCapacity(
            $orgId,
            $validated['from'],
            $validated['to'],
            $validated['strategy']
        );

        $rescheduledCount = count(array_filter($results, fn($r) => isset($r['new_start_date'])));

        return $this->success([
            'from'              => $validated['from'],
            'to'                => $validated['to'],
            'strategy'          => $validated['strategy'],
            'rescheduled_count' => $rescheduledCount,
            'actions'           => $results,
        ], "Capacity leveling applied: {$rescheduledCount} work order(s) rescheduled.");
    }

    /**
     * GET manufacturing/capacity-leveling/work-orders/{workOrder}/alternative-work-centers
     *
     * Find alternative work centers for a specific operation on a work order.
     */
    public function alternativeWorkCenters(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'operation_id' => 'required|integer',
        ]);

        $alternative = $this->capacityService->findAlternativeWorkCenter(
            $workOrder,
            (int) $validated['operation_id']
        );

        if ($alternative === null) {
            return $this->success(
                ['work_center' => null],
                'No alternative work center with sufficient capacity found.'
            );
        }

        return $this->success([
            'work_center' => [
                'id'               => $alternative->id,
                'code'             => $alternative->code,
                'name'             => $alternative->name,
                'work_center_type' => $alternative->work_center_type,
                'capacity_per_day' => (float) $alternative->capacity_per_day,
            ],
        ]);
    }
}

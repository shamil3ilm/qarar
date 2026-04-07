<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Services\CRM\CrmReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmReportController extends Controller
{
    public function __construct(private readonly CrmReportService $service) {}

    /**
     * GET /crm/reports/pipeline
     *
     * Pipeline report: open opportunities grouped by stage with weighted values.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $filters = $request->only(['assigned_to', 'from_date', 'to_date']);

        return $this->success(
            $this->service->getPipelineReport($request->user()->organization_id, $filters)
        );
    }

    /**
     * GET /crm/reports/win-loss
     *
     * Win/loss analysis over a date range.
     */
    public function winLoss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        return $this->success(
            $this->service->getWinLossAnalysis(
                $request->user()->organization_id,
                $validated['from_date'],
                $validated['to_date']
            )
        );
    }

    /**
     * GET /crm/reports/activities
     *
     * Activity analytics: completion and overdue rates by type.
     */
    public function activities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        return $this->success(
            $this->service->getActivityAnalytics(
                $request->user()->organization_id,
                $validated['from_date'],
                $validated['to_date']
            )
        );
    }

    /**
     * GET /crm/reports/lead-funnel
     *
     * Lead funnel with conversion rates per stage.
     */
    public function leadFunnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        return $this->success(
            $this->service->getLeadFunnel(
                $request->user()->organization_id,
                $validated['from_date'],
                $validated['to_date']
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Services\Maintenance\MaintenanceReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceReportController extends Controller
{
    public function __construct(private readonly MaintenanceReportingService $service)
    {
    }

    public function kpiDashboard(Request $request): JsonResponse
    {
        $orgId       = $request->user()->organization_id;
        $equipmentId = $request->integer('equipment_id') ?: null;
        $months      = $request->integer('months', 12);

        return $this->success($this->service->getKpiDashboard($orgId, $equipmentId, $months));
    }

    public function computeKpis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $this->service->computeKpis(
            $request->user()->organization_id,
            $validated['year'],
            $validated['month']
        );

        return $this->success(null, 'KPIs computed');
    }

    public function costAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $data = $this->service->getCostAnalysis(
            $request->user()->organization_id,
            $validated['from_date'],
            $validated['to_date']
        );

        return $this->success($data);
    }
}

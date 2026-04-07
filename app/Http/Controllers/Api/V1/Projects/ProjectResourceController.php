<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\ProjectResourcePlan;
use App\Models\Projects\ProjectTimeSheet;
use App\Services\Projects\ProjectResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectResourceController extends Controller
{
    public function __construct(private readonly ProjectResourceService $service) {}

    public function resourcePlans(Request $request): JsonResponse
    {
        $plans = ProjectResourcePlan::where('organization_id', $request->user()->organization_id)
            ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->paginate(20);

        return $this->paginated($plans);
    }

    public function storeResourcePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'           => 'required|integer',
            'wbs_element'          => 'nullable|string|max:50',
            'resource_type'        => 'required|in:labor,equipment,material,subcontractor',
            'resource_id'          => 'nullable|integer',
            'resource_description' => 'required|string|max:255',
            'planned_quantity'     => 'required|numeric|min:0',
            'uom'                  => 'required|string|max:20',
            'planned_start'        => 'required|date',
            'planned_end'          => 'required|date|after_or_equal:planned_start',
            'cost_rate'            => 'nullable|numeric|min:0',
            'planned_cost'         => 'nullable|numeric|min:0',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $plan = $this->service->planResource($data);

        return $this->created($plan);
    }

    public function utilization(Request $request, int $projectId): JsonResponse
    {
        $result = $this->service->getResourceUtilization($projectId);
        return $this->success($result);
    }

    public function timesheets(Request $request): JsonResponse
    {
        $sheets = ProjectTimeSheet::where('organization_id', $request->user()->organization_id)
            ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->with('employee')
            ->orderBy('work_date', 'desc')
            ->paginate(20);

        return $this->paginated($sheets);
    }

    public function submitTimesheet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'          => 'required|integer|exists:employees,id',
            'project_id'           => 'required|integer',
            'wbs_element'          => 'nullable|string|max:50',
            'work_date'            => 'required|date',
            'hours_worked'         => 'required|numeric|min:0.1|max:24',
            'activity_description' => 'nullable|string',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $sheet = $this->service->submitTimesheet($data);

        return $this->created($sheet);
    }

    public function approveTimesheet(Request $request, int $id): JsonResponse
    {
        $sheet = ProjectTimeSheet::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $this->service->approveTimesheet($sheet, $request->user()->id);

        return $this->success($sheet->fresh(), 'Timesheet approved');
    }
}

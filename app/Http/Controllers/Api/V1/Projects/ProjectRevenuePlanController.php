<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\ProjectRevenuePlan;
use App\Services\Projects\ProjectRevenuePlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRevenuePlanController extends Controller
{
    public function __construct(private readonly ProjectRevenuePlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $plans = ProjectRevenuePlan::where('organization_id', $request->user()->organization_id)
            ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->with('lines')
            ->paginate(20);

        return $this->paginated($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'  => 'required|integer',
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'version'     => 'nullable|string|max:10',
            'currency'    => 'required|string|size:3',
            'lines'       => 'nullable|array',
            'lines.*.period_month'    => 'required|integer|min:1|max:12',
            'lines.*.planned_revenue' => 'required|numeric|min:0',
            'lines.*.planned_cost'    => 'required|numeric|min:0',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $plan = $this->service->createPlan($data);

        return $this->created($plan);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = ProjectRevenuePlan::where('organization_id', $request->user()->organization_id)
            ->with('lines')
            ->findOrFail($id);

        return $this->success($plan);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $plan = ProjectRevenuePlan::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $this->service->approvePlan($plan, $request->user()->id);

        return $this->success($plan->fresh(), 'Revenue plan approved');
    }

    public function variance(Request $request, int $projectId): JsonResponse
    {
        $year   = (int) $request->input('fiscal_year', now()->year);
        $result = $this->service->getVarianceReport($projectId, $year);

        return $this->success($result);
    }
}

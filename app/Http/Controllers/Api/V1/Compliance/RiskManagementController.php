<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\GrcRisk;
use App\Models\Compliance\GrcRiskTreatment;
use App\Services\Compliance\RiskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskManagementController extends Controller
{
    public function __construct(
        private readonly RiskManagementService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $filters        = $request->only(['risk_type', 'risk_status', 'min_residual_score', 'per_page']);

        $risks = $this->service->listRisks($organizationId, $filters);

        return $this->paginated($risks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['required', 'string'],
            'category_id'         => ['nullable', 'integer', 'exists:grc_risk_categories,id'],
            'risk_type'           => ['required', 'in:strategic,operational,financial,compliance,reputational,it,ehs'],
            'inherent_likelihood' => ['nullable', 'integer', 'min:1', 'max:5'],
            'inherent_impact'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'residual_likelihood' => ['nullable', 'integer', 'min:1', 'max:5'],
            'residual_impact'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'risk_owner_id'       => ['nullable', 'integer', 'exists:users,id'],
            'module_reference'    => ['nullable', 'string', 'max:50'],
            'existing_controls'   => ['nullable', 'string'],
            'next_review_date'    => ['nullable', 'date'],
            'identified_date'     => ['nullable', 'date'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $risk = $this->service->createRisk($organizationId, $data, $userId);

        return $this->created($risk->load(['category', 'riskOwner']), 'Risk created');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $risk           = $this->service->findRisk($organizationId, $uuid);

        return $this->success($risk);
    }

    public function assess(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'inherent_likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'inherent_impact'     => ['required', 'integer', 'min:1', 'max:5'],
            'residual_likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'residual_impact'     => ['required', 'integer', 'min:1', 'max:5'],
            'existing_controls'   => ['nullable', 'string'],
            'next_review_date'    => ['nullable', 'date'],
            'review_notes'        => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $risk = $this->service->assessRisk($organizationId, $uuid, $data, $userId);

        return $this->success($risk, 'Risk assessed');
    }

    public function addTreatment(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'treatment_type'    => ['required', 'in:avoid,reduce,transfer,accept'],
            'description'       => ['required', 'string'],
            'action_plan'       => ['nullable', 'string'],
            'target_date'       => ['nullable', 'date'],
            'owner_id'          => ['nullable', 'integer', 'exists:users,id'],
            'target_likelihood' => ['nullable', 'integer', 'min:1', 'max:5'],
            'target_impact'     => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $treatment = $this->service->addTreatment($organizationId, $uuid, $data, $userId);

        return $this->created($treatment, 'Treatment plan added');
    }

    public function updateTreatment(Request $request, string $treatmentUuid): JsonResponse
    {
        $data = $request->validate([
            'treatment_type'    => ['sometimes', 'in:avoid,reduce,transfer,accept'],
            'description'       => ['sometimes', 'string'],
            'action_plan'       => ['nullable', 'string'],
            'target_date'       => ['nullable', 'date'],
            'completed_date'    => ['nullable', 'date'],
            'status'            => ['sometimes', 'in:planned,in_progress,completed,cancelled'],
            'owner_id'          => ['nullable', 'integer', 'exists:users,id'],
            'target_likelihood' => ['nullable', 'integer', 'min:1', 'max:5'],
            'target_impact'     => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $organizationId = $this->organizationId($request);

        $treatment = $this->service->updateTreatment($organizationId, $treatmentUuid, $data);

        return $this->success($treatment, 'Treatment updated');
    }

    public function heatMap(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        return $this->success($this->service->getRiskHeatMap($organizationId));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        return $this->success($this->service->getDashboard($organizationId));
    }

    public function indexCategories(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $categories = $this->service->listCategories($organizationId);

        return $this->success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => ['required', 'string', 'max:20'],
            'name'      => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:grc_risk_categories,id'],
            'risk_type' => ['required', 'in:strategic,operational,financial,compliance,reputational,it,ehs'],
            'is_active' => ['boolean'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $category = $this->service->createCategory($organizationId, $data, $userId);

        return $this->created($category, 'Risk category created');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Resources\Projects\ProjectBudgetLineItemResource;
use App\Http\Resources\Projects\ProjectBudgetSupplementResource;
use App\Http\Resources\Projects\ProjectBudgetVersionResource;
use App\Models\Projects\ProjectBudgetLineItem;
use App\Models\Projects\ProjectBudgetSupplement;
use App\Models\Projects\ProjectBudgetVersion;
use App\Services\Projects\ProjectBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectBudgetController extends Controller
{
    public function __construct(
        private ProjectBudgetService $budgetService,
    ) {}

    // ── Versions ──────────────────────────────────────────────────────────────

    public function listVersions(Request $request, int|string $projectId): JsonResponse
    {
        $filters = $request->only(['status', 'fiscal_year', 'is_current']);
        $perPage = (int) $request->get('per_page', 20);

        $paginator = $this->budgetService->listVersions((int) $projectId, $filters, $perPage);

        return $this->success(
            ProjectBudgetVersionResource::collection($paginator)->response()->getData(true)
        );
    }

    public function createVersion(Request $request, int|string $projectId): JsonResponse
    {
        $validated = $request->validate([
            'version_code' => ['required', 'string', 'max:20'],
            'version_name' => ['required', 'string', 'max:100'],
            'fiscal_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'status'       => ['sometimes', Rule::in(['draft', 'active', 'frozen', 'archived'])],
            'is_current'   => ['sometimes', 'boolean'],
            'total_budget' => ['sometimes', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        $validated['project_id'] = (int) $projectId;

        $version = $this->budgetService->createVersion($validated);

        return $this->created(new ProjectBudgetVersionResource($version));
    }

    public function showVersion(int|string $projectId, int|string $versionId): JsonResponse
    {
        $version = ProjectBudgetVersion::with(['lineItems', 'supplements', 'approvedBy'])
            ->where('project_id', (int) $projectId)
            ->findOrFail((int) $versionId);

        return $this->success(new ProjectBudgetVersionResource($version));
    }

    public function updateVersion(Request $request, int|string $projectId, int|string $versionId): JsonResponse
    {
        $version = ProjectBudgetVersion::where('project_id', (int) $projectId)
            ->findOrFail((int) $versionId);

        $validated = $request->validate([
            'version_code' => ['sometimes', 'string', 'max:20'],
            'version_name' => ['sometimes', 'string', 'max:100'],
            'fiscal_year'  => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'notes'        => ['nullable', 'string'],
        ]);

        $version = $this->budgetService->updateVersion($version, $validated);

        return $this->success(new ProjectBudgetVersionResource($version));
    }

    public function activateVersion(int|string $projectId, int|string $versionId): JsonResponse
    {
        $version = ProjectBudgetVersion::where('project_id', (int) $projectId)
            ->findOrFail((int) $versionId);

        $version = $this->budgetService->activateVersion($version);

        return $this->success(new ProjectBudgetVersionResource($version), 'Budget version activated.');
    }

    // ── Line items ────────────────────────────────────────────────────────────

    public function setLineItems(Request $request, int|string $projectId, int|string $versionId): JsonResponse
    {
        $version = ProjectBudgetVersion::where('project_id', (int) $projectId)
            ->findOrFail((int) $versionId);

        $request->validate([
            'lines'                      => ['required', 'array'],
            'lines.*.wbs_element_id'     => ['nullable', 'integer', 'exists:wbs_elements,id'],
            'lines.*.cost_element_id'    => ['nullable', 'integer', 'exists:cost_elements,id'],
            'lines.*.budgeted_amount'    => ['required', 'numeric', 'min:0'],
            'lines.*.avac_action'        => ['sometimes', Rule::in(['warning', 'error', 'none'])],
            'lines.*.tolerance_percent'  => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->budgetService->setLineItems($version, $request->input('lines', []));

        $version->load('lineItems');

        return $this->success(
            ProjectBudgetLineItemResource::collection($version->lineItems),
            'Line items updated.'
        );
    }

    // ── Supplements ───────────────────────────────────────────────────────────

    public function createSupplement(
        Request $request,
        int|string $projectId,
        int|string $versionId
    ): JsonResponse {
        $version = ProjectBudgetVersion::where('project_id', (int) $projectId)
            ->findOrFail((int) $versionId);

        $validated = $request->validate([
            'wbs_element_id'  => ['nullable', 'integer', 'exists:wbs_elements,id'],
            'supplement_type' => ['sometimes', Rule::in(['supplement', 'return', 'transfer_in', 'transfer_out'])],
            'amount'          => ['required', 'numeric'],
            'reason'          => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['project_budget_version_id'] = $version->id;

        $supplement = $this->budgetService->createSupplement($validated);

        return $this->created(new ProjectBudgetSupplementResource($supplement));
    }

    public function approveSupplement(
        int|string $projectId,
        int|string $versionId,
        int|string $supplementId
    ): JsonResponse {
        $supplement = ProjectBudgetSupplement::where('project_budget_version_id', (int) $versionId)
            ->findOrFail((int) $supplementId);

        $supplement = $this->budgetService->approveSupplement($supplement, (int) auth()->id());

        return $this->success(new ProjectBudgetSupplementResource($supplement), 'Supplement approved.');
    }

    public function rejectSupplement(
        int|string $projectId,
        int|string $versionId,
        int|string $supplementId
    ): JsonResponse {
        $supplement = ProjectBudgetSupplement::where('project_budget_version_id', (int) $versionId)
            ->findOrFail((int) $supplementId);

        $supplement = $this->budgetService->rejectSupplement($supplement);

        return $this->success(new ProjectBudgetSupplementResource($supplement), 'Supplement rejected.');
    }

    // ── Availability check ────────────────────────────────────────────────────

    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wbs_element_id'  => ['required', 'integer', 'exists:wbs_elements,id'],
            'amount'          => ['required', 'numeric', 'min:0'],
            'document_type'   => ['required', 'string', 'max:50'],
            'document_id'     => ['required', 'integer'],
            'cost_element_id' => ['nullable', 'integer', 'exists:cost_elements,id'],
        ]);

        $result = $this->budgetService->checkAvailability(
            wbsElementId:  (int) $validated['wbs_element_id'],
            amount:        (float) $validated['amount'],
            documentType:  $validated['document_type'],
            documentId:    (int) $validated['document_id'],
            costElementId: isset($validated['cost_element_id']) ? (int) $validated['cost_element_id'] : null,
        );

        $statusCode = $result['result'] === 'rejected' ? 422 : 200;

        return $this->success($result, 'Availability check completed.', $statusCode);
    }

    // ── Budget status ─────────────────────────────────────────────────────────

    public function budgetStatus(int|string $projectId): JsonResponse
    {
        $status = $this->budgetService->getBudgetStatus((int) $projectId);

        return $this->success($status);
    }
}

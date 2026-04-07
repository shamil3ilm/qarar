<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\WorkflowEscalationRule;
use App\Models\Core\WorkflowSubstitutionRule;
use App\Services\Core\WorkflowEscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowEscalationController extends Controller
{
    public function __construct(
        private readonly WorkflowEscalationService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Escalation Rules
    // -------------------------------------------------------------------------

    public function indexRules(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $results = $this->service->listRules(
            orgId: $orgId,
            filters: $request->only(['approval_workflow_id', 'is_active']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approval_workflow_id'  => ['nullable', 'exists:approval_workflows,id'],
            'step_number'           => ['nullable', 'integer', 'min:1'],
            'escalation_type'       => ['required', 'in:reminder,escalate_to_manager,escalate_to_admin,auto_approve,auto_reject'],
            'trigger_after_hours'   => ['required', 'integer', 'min:1'],
            'escalate_to_user_id'   => ['nullable', 'exists:users,id'],
            'escalate_to_role'      => ['nullable', 'string', 'max:100'],
            'notification_template' => ['nullable', 'string'],
            'is_active'             => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $rule = $this->service->createRule($validated);

        return $this->created($rule, 'Escalation rule created.');
    }

    public function updateRule(Request $request, string $id): JsonResponse
    {
        $rule = WorkflowEscalationRule::findOrFail($id);

        $validated = $request->validate([
            'escalation_type'       => ['sometimes', 'in:reminder,escalate_to_manager,escalate_to_admin,auto_approve,auto_reject'],
            'trigger_after_hours'   => ['sometimes', 'integer', 'min:1'],
            'escalate_to_user_id'   => ['nullable', 'exists:users,id'],
            'escalate_to_role'      => ['nullable', 'string', 'max:100'],
            'notification_template' => ['nullable', 'string'],
            'is_active'             => ['nullable', 'boolean'],
        ]);

        $rule = $this->service->updateRule($rule, $validated);

        return $this->success($rule, 'Escalation rule updated.');
    }

    public function destroyRule(string $id): JsonResponse
    {
        $rule = WorkflowEscalationRule::findOrFail($id);
        $this->service->deleteRule($rule);

        return $this->success(null, 'Escalation rule deleted.');
    }

    // -------------------------------------------------------------------------
    // Escalation Engine
    // -------------------------------------------------------------------------

    public function checkAndEscalate(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $results = $this->service->checkAndEscalate($orgId);

        return $this->success([
            'processed' => count($results),
            'actions'   => $results,
        ], count($results) . ' escalation action(s) taken.');
    }

    // -------------------------------------------------------------------------
    // Substitutions
    // -------------------------------------------------------------------------

    public function indexSubstitutions(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $results = $this->service->listSubstitutions(
            orgId: $orgId,
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function createSubstitution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approver_id'   => ['required', 'exists:users,id'],
            'substitute_id' => ['required', 'exists:users,id', 'different:approver_id'],
            'valid_from'    => ['required', 'date'],
            'valid_to'      => ['nullable', 'date', 'after_or_equal:valid_from'],
            'reason'        => ['nullable', 'string'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $substitution = $this->service->createSubstitution($validated);

            return $this->created($substitution, 'Substitution rule created.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    public function revokeSubstitution(string $id): JsonResponse
    {
        $rule = WorkflowSubstitutionRule::findOrFail($id);
        $this->service->revokeSubstitution($rule);

        return $this->success(null, 'Substitution rule revoked.');
    }

    public function getSubstitute(string $approverId): JsonResponse
    {
        $substitute = $this->service->getSubstituteFor((int) $approverId);

        if ($substitute === null) {
            return $this->success(null, 'No active substitute found for this approver.');
        }

        return $this->success([
            'substitute_id'   => $substitute->id,
            'substitute_name' => $substitute->name,
        ]);
    }
}

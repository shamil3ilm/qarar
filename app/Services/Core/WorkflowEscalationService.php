<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ApprovalRequest;
use App\Models\Core\WorkflowEscalationLog;
use App\Models\Core\WorkflowEscalationRule;
use App\Models\Core\WorkflowSubstitutionRule;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowEscalationService
{
    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public function createRule(array $data): WorkflowEscalationRule
    {
        return DB::transaction(static fn () => WorkflowEscalationRule::create($data));
    }

    public function updateRule(WorkflowEscalationRule $rule, array $data): WorkflowEscalationRule
    {
        DB::transaction(static fn () => $rule->update($data));

        return $rule->fresh();
    }

    public function deleteRule(WorkflowEscalationRule $rule): void
    {
        $rule->delete();
    }

    public function listRules(int $orgId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = WorkflowEscalationRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with(['workflow:id,name', 'escalateTo:id,name'])
            ->orderBy('trigger_after_hours');

        if (!empty($filters['approval_workflow_id'])) {
            $query->where('approval_workflow_id', $filters['approval_workflow_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Escalation engine
    // -------------------------------------------------------------------------

    /**
     * Check all pending approval requests and apply escalation rules.
     * Returns a summary of actions taken.
     */
    public function checkAndEscalate(int $orgId): array
    {
        $results = [];

        // Load active rules for this org
        $rules = WorkflowEscalationRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->active()
            ->orderBy('trigger_after_hours')
            ->get();

        if ($rules->isEmpty()) {
            return $results;
        }

        // Find pending/in-progress requests that have been waiting
        $requests = ApprovalRequest::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('submitted_at')
            ->get();

        foreach ($requests as $request) {
            $hoursPending = (int) $request->submitted_at->diffInHours(now());

            foreach ($rules as $rule) {
                // Check if this rule applies to the request's workflow
                if ($rule->approval_workflow_id !== null && $rule->approval_workflow_id !== $request->approval_workflow_id) {
                    continue;
                }

                if ($hoursPending < $rule->trigger_after_hours) {
                    continue;
                }

                // Check if this rule was already triggered for this request
                $alreadyLogged = WorkflowEscalationLog::where('approval_request_id', $request->id)
                    ->where('workflow_escalation_rule_id', $rule->id)
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $actionTaken = $this->applyEscalation($request, $rule, $orgId);

                $results[] = [
                    'approval_request_id' => $request->id,
                    'rule_id'             => $rule->id,
                    'escalation_type'     => $rule->escalation_type,
                    'action_taken'        => $actionTaken,
                ];
            }
        }

        return $results;
    }

    private function applyEscalation(ApprovalRequest $request, WorkflowEscalationRule $rule, int $orgId): string
    {
        $escalatedToUserId = null;
        $actionTaken = $rule->escalation_type;

        if ($rule->escalation_type === 'auto_approve') {
            $request->update(['status' => ApprovalRequest::STATUS_APPROVED, 'completed_at' => now()]);
            $actionTaken = 'auto_approved';
        } elseif ($rule->escalation_type === 'auto_reject') {
            $request->update(['status' => ApprovalRequest::STATUS_REJECTED, 'completed_at' => now()]);
            $actionTaken = 'auto_rejected';
        } elseif ($rule->escalate_to_user_id !== null) {
            $escalatedToUserId = $rule->escalate_to_user_id;
            $actionTaken = "escalated_to_user:{$escalatedToUserId}";
        }

        WorkflowEscalationLog::create([
            'organization_id'             => $orgId,
            'approval_request_id'         => $request->id,
            'workflow_escalation_rule_id' => $rule->id,
            'escalation_type'             => $rule->escalation_type,
            'triggered_at'                => now(),
            'escalated_to_user_id'        => $escalatedToUserId,
            'action_taken'                => $actionTaken,
        ]);

        return $actionTaken;
    }

    // -------------------------------------------------------------------------
    // Substitutions
    // -------------------------------------------------------------------------

    public function getSubstituteFor(int $approverId): ?User
    {
        $rule = WorkflowSubstitutionRule::active()
            ->forApprover($approverId)
            ->validNow()
            ->latest('valid_from')
            ->first();

        if ($rule === null) {
            return null;
        }

        return User::find($rule->substitute_id);
    }

    public function createSubstitution(array $data): WorkflowSubstitutionRule
    {
        // Validate no overlapping active substitution for the same approver
        $overlap = WorkflowSubstitutionRule::withoutGlobalScopes()
            ->where('organization_id', $data['organization_id'])
            ->where('approver_id', $data['approver_id'])
            ->where('is_active', true)
            ->where(function ($q) use ($data): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $data['valid_from']);
            })
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException('An active substitution already exists for this approver in the given period.');
        }

        return DB::transaction(static fn () => WorkflowSubstitutionRule::create($data));
    }

    public function revokeSubstitution(WorkflowSubstitutionRule $rule): void
    {
        $rule->update(['is_active' => false, 'valid_to' => now()->toDateString()]);
    }

    public function listSubstitutions(int $orgId, int $perPage = 20): LengthAwarePaginator
    {
        return WorkflowSubstitutionRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with(['approver:id,name', 'substitute:id,name'])
            ->orderByDesc('valid_from')
            ->paginate($perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Http\Controllers\Controller;
use App\Models\Core\ApprovalAction;
use App\Models\Core\ApprovalRequest;
use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\ApprovalWorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    /**
     * List approval workflows for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalWorkflow::with('steps')
            ->when($request->approvable_type, fn($q, $type) => $q->where('approvable_type', $type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $workflows = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($workflows);
    }

    /**
     * Create a new approval workflow.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'approvable_type' => ['required', 'string', 'max:100'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'steps' => ['nullable', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', 'string', 'in:user,role'],
            'steps.*.approver_id' => ['required', 'integer'],
            'steps.*.sequence' => ['required', 'integer', 'min:0'],
            'steps.*.condition' => ['nullable', 'array'],
        ]);

        $workflow = DB::transaction(function () use ($validated, $request) {
            $workflow = ApprovalWorkflow::create([
                'organization_id' => $this->organizationId($request),
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'approvable_type' => $validated['approvable_type'],
                'min_amount' => $validated['min_amount'] ?? null,
                'max_amount' => $validated['max_amount'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'priority' => $validated['priority'] ?? 0,
            ]);

            if (!empty($validated['steps'])) {
                foreach ($validated['steps'] as $stepData) {
                    ApprovalWorkflowStep::create([
                        'approval_workflow_id' => $workflow->id,
                        'name' => $stepData['name'],
                        'approver_type' => $stepData['approver_type'],
                        'approver_id' => $stepData['approver_id'],
                        'sequence' => $stepData['sequence'],
                        'action_type' => 'approve',
                        'conditions' => $stepData['condition'] ?? null,
                    ]);
                }
            }

            return $workflow->load('steps');
        });

        return $this->created($workflow, 'Approval workflow created successfully');
    }

    /**
     * Show a specific approval workflow.
     */
    public function show(int $id): JsonResponse
    {
        $workflow = ApprovalWorkflow::with('steps')
            ->where('organization_id', auth()->user()->organization_id)
            ->find($id);

        if (!$workflow) {
            return $this->notFound('Workflow not found');
        }

        return $this->success($workflow);
    }

    /**
     * Update an approval workflow.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workflow = ApprovalWorkflow::where('organization_id', auth()->user()->organization_id)
            ->find($id);

        if (!$workflow) {
            return $this->notFound('Workflow not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'approvable_type' => ['sometimes', 'string', 'max:100'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        $workflow->update($validated);

        return $this->success($workflow->load('steps'), 'Workflow updated successfully');
    }

    /**
     * List pending approval requests for the authenticated user.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $query = ApprovalRequest::with(['workflow', 'currentStep', 'submittedBy'])
            ->where('organization_id', auth()->user()->organization_id)
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_IN_PROGRESS])
            ->whereHas('actions', function ($q) {
                $q->where('assigned_to', auth()->id())
                    ->where('status', ApprovalAction::STATUS_PENDING);
            })
            ->orderByDesc('submitted_at');

        $requests = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($requests);
    }

    /**
     * Approve an approval request.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvalRequest = ApprovalRequest::with(['workflow', 'actions'])
            ->where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        $pendingAction = $approvalRequest->actions()
            ->where('workflow_step_id', $approvalRequest->current_step_id)
            ->where('assigned_to', auth()->id())
            ->where('status', ApprovalAction::STATUS_PENDING)
            ->first();

        if (!$pendingAction) {
            return $this->error('No pending action found for this user', 'NO_PENDING_ACTION', 422);
        }

        DB::transaction(function () use ($pendingAction, $approvalRequest, $validated) {
            $pendingAction->approve($validated['comments'] ?? null);

            // Check if all actions for current step are completed
            $remainingPending = $approvalRequest->actions()
                ->where('workflow_step_id', $approvalRequest->current_step_id)
                ->where('status', ApprovalAction::STATUS_PENDING)
                ->count();

            if ($remainingPending === 0) {
                // Move to next step or approve
                $nextStep = $approvalRequest->workflow->getNextStep($approvalRequest->currentStep);

                if ($nextStep) {
                    $approvalRequest->update([
                        'current_step_id' => $nextStep->id,
                        'status' => ApprovalRequest::STATUS_IN_PROGRESS,
                    ]);
                } else {
                    $approvalRequest->update([
                        'status' => ApprovalRequest::STATUS_APPROVED,
                        'completed_at' => now(),
                    ]);
                }
            }
        });

        return $this->success($approvalRequest->fresh()->load(['workflow', 'actions']), 'Request approved successfully');
    }

    /**
     * Reject an approval request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:1000'],
        ]);

        $approvalRequest = ApprovalRequest::with(['workflow', 'actions'])
            ->where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        $pendingAction = $approvalRequest->actions()
            ->where('workflow_step_id', $approvalRequest->current_step_id)
            ->where('assigned_to', auth()->id())
            ->where('status', ApprovalAction::STATUS_PENDING)
            ->first();

        if (!$pendingAction) {
            return $this->error('No pending action found for this user', 'NO_PENDING_ACTION', 422);
        }

        DB::transaction(function () use ($pendingAction, $approvalRequest, $validated) {
            $pendingAction->reject($validated['comments']);

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'completed_at' => now(),
            ]);
        });

        return $this->success($approvalRequest->fresh()->load(['workflow', 'actions']), 'Request rejected');
    }

    /**
     * List approval history (completed approval requests).
     */
    public function history(Request $request): JsonResponse
    {
        $query = ApprovalRequest::with(['workflow', 'submittedBy', 'actions.actionBy'])
            ->where('organization_id', auth()->user()->organization_id)
            ->whereIn('status', [
                ApprovalRequest::STATUS_APPROVED,
                ApprovalRequest::STATUS_REJECTED,
                ApprovalRequest::STATUS_CANCELLED,
            ])
            ->orderByDesc('completed_at');

        $requests = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($requests);
    }
}

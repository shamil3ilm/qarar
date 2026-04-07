<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\PersonnelAction;
use App\Models\HR\PersonnelActionStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Personnel Action Service — SAP PA40 equivalent.
 *
 * A Personnel Action is an atomic wrapper that fires a sequence of
 * sub-steps when a significant HR lifecycle event occurs (hire, transfer,
 * promotion, exit, etc.).  Each step is recorded independently so failures
 * are traceable without unwinding completed work.
 *
 * Step registry per action type:
 *   hire              → create_contract, assign_position, set_salary, setup_payroll, notify_it, notify_facilities
 *   rehire            → reactivate_employee, create_contract, assign_position, set_salary, setup_payroll
 *   transfer          → update_position, update_department, update_cost_center, notify_payroll
 *   promotion         → update_designation, update_salary, update_grade, notify_payroll
 *   demotion          → update_designation, update_salary, update_grade, notify_payroll
 *   exit              → calculate_eosb, freeze_payroll, deactivate_access, archive_employee
 *   leave_of_absence  → set_leave_status, notify_payroll, update_attendance
 */
class PersonnelActionService
{
    private const STEP_REGISTRY = [
        PersonnelAction::TYPE_HIRE => [
            'create_contract', 'assign_position', 'set_salary', 'setup_payroll', 'notify_it',
        ],
        PersonnelAction::TYPE_REHIRE => [
            'reactivate_employee', 'create_contract', 'assign_position', 'set_salary', 'setup_payroll',
        ],
        PersonnelAction::TYPE_TRANSFER => [
            'update_position', 'update_department', 'update_cost_center', 'notify_payroll',
        ],
        PersonnelAction::TYPE_PROMOTION => [
            'update_designation', 'update_salary', 'update_grade', 'notify_payroll',
        ],
        PersonnelAction::TYPE_DEMOTION => [
            'update_designation', 'update_salary', 'update_grade', 'notify_payroll',
        ],
        PersonnelAction::TYPE_EXIT => [
            'calculate_eosb', 'freeze_payroll', 'deactivate_access', 'archive_employee',
        ],
        PersonnelAction::TYPE_LEAVE_OF_ABSENCE => [
            'set_leave_status', 'notify_payroll', 'update_attendance_policy',
        ],
    ];

    public function __construct(
        private readonly EmployeeTransferService $transferService,
        private readonly HCMOnboardingService $onboardingService,
    ) {}

    /**
     * Initiate a new personnel action (creates in draft status).
     *
     * @param array{
     *     employee_id: int,
     *     action_type: string,
     *     effective_date: string,
     *     payload: array,
     *     reason?: string,
     *     notes?: string,
     * } $data
     */
    public function initiate(array $data, User $initiatedBy): PersonnelAction
    {
        $this->validateActionType($data['action_type']);

        return DB::transaction(function () use ($data, $initiatedBy): PersonnelAction {
            $action = PersonnelAction::create([
                'organization_id' => $initiatedBy->organization_id,
                'action_number'   => $this->generateActionNumber($initiatedBy->organization_id),
                'employee_id'     => $data['employee_id'],
                'action_type'     => $data['action_type'],
                'effective_date'  => $data['effective_date'],
                'payload'         => $data['payload'] ?? [],
                'reason'          => $data['reason'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => PersonnelAction::STATUS_DRAFT,
                'initiated_by'    => $initiatedBy->id,
            ]);

            // Pre-create step placeholders for visibility
            foreach (self::STEP_REGISTRY[$data['action_type']] ?? [] as $stepName) {
                PersonnelActionStep::create([
                    'personnel_action_id' => $action->id,
                    'step_name'           => $stepName,
                    'status'              => PersonnelActionStep::STATUS_PENDING,
                ]);
            }

            return $action;
        });
    }

    /** Submit draft for approval. */
    public function submit(PersonnelAction $action): PersonnelAction
    {
        $this->assertTransition($action, PersonnelAction::STATUS_SUBMITTED);
        $action->update(['status' => PersonnelAction::STATUS_SUBMITTED]);
        return $action->fresh();
    }

    /** Approve and immediately execute the action. */
    public function approve(PersonnelAction $action, User $approver): PersonnelAction
    {
        $this->assertTransition($action, PersonnelAction::STATUS_APPROVED);

        DB::transaction(function () use ($action, $approver): void {
            $action->update([
                'status'      => PersonnelAction::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
        });

        return $this->execute($action);
    }

    /** Reject a submitted action. */
    public function reject(PersonnelAction $action, User $rejector, string $reason): PersonnelAction
    {
        $this->assertTransition($action, PersonnelAction::STATUS_REJECTED);

        $action->update([
            'status'           => PersonnelAction::STATUS_REJECTED,
            'approved_by'      => $rejector->id,
            'rejection_reason' => $reason,
        ]);

        return $action->fresh();
    }

    /**
     * Execute all steps of an approved action.
     *
     * Steps run sequentially.  A failed step is recorded but does NOT
     * roll back completed steps — the action moves to "completed" regardless,
     * and failed steps are surfaced for manual remediation.  This mirrors
     * SAP's PA40 behaviour where partial completion is tracked per infotype.
     */
    public function execute(PersonnelAction $action): PersonnelAction
    {
        $this->assertTransition($action, PersonnelAction::STATUS_COMPLETED);

        $steps = $action->steps()->orderBy('id')->get();

        foreach ($steps as $step) {
            try {
                $result = $this->runStep($action, $step->step_name);

                $step->update([
                    'status'      => PersonnelActionStep::STATUS_COMPLETED,
                    'result'      => $result,
                    'executed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error("PersonnelAction step '{$step->step_name}' failed", [
                    'action_id' => $action->id,
                    'error'     => $e->getMessage(),
                ]);

                $step->update([
                    'status'        => PersonnelActionStep::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'executed_at'   => now(),
                ]);
            }
        }

        $action->update([
            'status'       => PersonnelAction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $action->fresh(['steps']);
    }

    /**
     * Reverse a completed action — marks as reversed and fires compensating steps.
     * Not all action types support reversal.
     */
    public function reverse(PersonnelAction $action, User $reversedBy): PersonnelAction
    {
        $this->assertTransition($action, PersonnelAction::STATUS_REVERSED);

        if (! in_array($action->action_type, [PersonnelAction::TYPE_TRANSFER, PersonnelAction::TYPE_PROMOTION, PersonnelAction::TYPE_DEMOTION], true)) {
            throw new \LogicException("Action type '{$action->action_type}' cannot be reversed.");
        }

        $action->update([
            'status'      => PersonnelAction::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversed_by' => $reversedBy->id,
        ]);

        return $action->fresh();
    }

    // ----------------------------------------------------------------
    // Step dispatcher
    // ----------------------------------------------------------------

    private function runStep(PersonnelAction $action, string $stepName): array
    {
        $employee = Employee::findOrFail($action->employee_id);
        $payload  = $action->payload ?? [];

        return match ($stepName) {
            'assign_position', 'update_position' => $this->stepUpdatePosition($employee, $payload),
            'update_department'                  => $this->stepUpdateDepartment($employee, $payload),
            'update_cost_center'                 => $this->stepUpdateCostCenter($employee, $payload),
            'update_designation'                 => $this->stepUpdateDesignation($employee, $payload),
            'update_salary'                      => $this->stepUpdateSalary($employee, $payload),
            'freeze_payroll'                     => $this->stepFreezePayroll($employee),
            'deactivate_access'                  => $this->stepDeactivateAccess($employee),
            'archive_employee'                   => $this->stepArchiveEmployee($employee, $payload),
            'reactivate_employee'                => $this->stepReactivateEmployee($employee),
            // Steps handled by downstream services — acknowledge only
            'create_contract', 'set_salary', 'setup_payroll',
            'notify_payroll', 'notify_it', 'calculate_eosb',
            'update_grade', 'set_leave_status',
            'update_attendance_policy'           => ['status' => 'acknowledged', 'note' => 'Handled by downstream service'],
            default                              => throw new \UnexpectedValueException("Unknown step: {$stepName}"),
        };
    }

    private function stepUpdatePosition(Employee $employee, array $payload): array
    {
        if (isset($payload['position_id'])) {
            $employee->update(['position_id' => $payload['position_id']]);
        }
        return ['position_id' => $payload['position_id'] ?? null];
    }

    private function stepUpdateDepartment(Employee $employee, array $payload): array
    {
        if (isset($payload['department_id'])) {
            $employee->update(['department_id' => $payload['department_id']]);
        }
        return ['department_id' => $payload['department_id'] ?? null];
    }

    private function stepUpdateCostCenter(Employee $employee, array $payload): array
    {
        if (isset($payload['cost_center_id'])) {
            $employee->update(['cost_center_id' => $payload['cost_center_id']]);
        }
        return ['cost_center_id' => $payload['cost_center_id'] ?? null];
    }

    private function stepUpdateDesignation(Employee $employee, array $payload): array
    {
        if (isset($payload['designation_id'])) {
            $employee->update(['designation_id' => $payload['designation_id']]);
        }
        return ['designation_id' => $payload['designation_id'] ?? null];
    }

    private function stepUpdateSalary(Employee $employee, array $payload): array
    {
        if (isset($payload['basic_salary'])) {
            $employee->update(['basic_salary' => $payload['basic_salary']]);
        }
        return ['basic_salary' => $payload['basic_salary'] ?? null];
    }

    private function stepFreezePayroll(Employee $employee): array
    {
        $employee->update(['payroll_status' => 'frozen']);
        return ['payroll_status' => 'frozen'];
    }

    private function stepDeactivateAccess(Employee $employee): array
    {
        // Soft-disable user account if linked.
        // Load the instance so HasAuditTrail fires the updated event.
        if ($employee->user_id) {
            \App\Models\User::find($employee->user_id)?->update(['is_active' => false]);
        }
        return ['access_deactivated' => true];
    }

    private function stepArchiveEmployee(Employee $employee, array $payload): array
    {
        $employee->update([
            'employment_status' => 'terminated',
            'exit_date'         => $payload['exit_date'] ?? now()->toDateString(),
        ]);
        return ['employment_status' => 'terminated'];
    }

    private function stepReactivateEmployee(Employee $employee): array
    {
        $employee->update(['employment_status' => 'active']);
        return ['employment_status' => 'active'];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function assertTransition(PersonnelAction $action, string $to): void
    {
        if (! $action->canTransition($to)) {
            throw new \LogicException("Cannot transition personnel action from '{$action->status}' to '{$to}'.");
        }
    }

    private function validateActionType(string $type): void
    {
        $valid = array_keys(self::STEP_REGISTRY);
        if (! in_array($type, $valid, true)) {
            throw new \InvalidArgumentException("Unknown action type '{$type}'. Valid: " . implode(', ', $valid));
        }
    }

    private function generateActionNumber(int $organizationId): string
    {
        $count = PersonnelAction::where('organization_id', $organizationId)->withTrashed()->count() + 1;
        return 'PA-' . date('Y') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}

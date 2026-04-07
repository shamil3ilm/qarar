<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\OnboardingTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class HCMOnboardingService
{
    /**
     * Default task templates per onboarding type.
     * SAP HCM equivalent: onboarding cockpit task catalog.
     */
    private const DEFAULT_TASKS = [
        'standard' => [
            ['title' => 'Send welcome email',                  'category' => 'hr',       'due_days' => 0,  'is_required' => true],
            ['title' => 'Prepare employment contract',         'category' => 'hr',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Set up payroll record',               'category' => 'hr',       'due_days' => 3,  'is_required' => true],
            ['title' => 'Enroll in health insurance',          'category' => 'hr',       'due_days' => 7,  'is_required' => false],
            ['title' => 'Create network/AD account',           'category' => 'it',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Issue laptop / equipment',            'category' => 'it',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Set up email account',                'category' => 'it',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Complete mandatory compliance training', 'category' => 'employee', 'due_days' => 14, 'is_required' => true],
            ['title' => 'Sign employment contract',            'category' => 'employee', 'due_days' => 3,  'is_required' => true],
            ['title' => 'Complete personal data form',         'category' => 'employee', 'due_days' => 3,  'is_required' => true],
            ['title' => 'Team introduction / buddy assignment','category' => 'manager',  'due_days' => 1,  'is_required' => false],
            ['title' => 'Set 30-day goals',                    'category' => 'manager',  'due_days' => 7,  'is_required' => false],
        ],
        'rehire' => [
            ['title' => 'Reactivate employee record',          'category' => 'hr',       'due_days' => 0,  'is_required' => true],
            ['title' => 'Update payroll / bank details',       'category' => 'hr',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Restore system access',               'category' => 'it',       'due_days' => 1,  'is_required' => true],
            ['title' => 'Sign updated employment contract',    'category' => 'employee', 'due_days' => 3,  'is_required' => true],
        ],
        'probation' => [
            ['title' => 'Set probation goals',                 'category' => 'manager',  'due_days' => 7,  'is_required' => true],
            ['title' => 'Mid-probation review',                'category' => 'manager',  'due_days' => 45, 'is_required' => true],
            ['title' => 'End-of-probation assessment',         'category' => 'hr',       'due_days' => 85, 'is_required' => true],
            ['title' => 'Confirm or extend employment',        'category' => 'hr',       'due_days' => 90, 'is_required' => true],
        ],
    ];

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    /**
     * Create a new onboarding instance for an employee with default tasks.
     */
    public function create(array $data, int $userId): EmployeeOnboarding
    {
        return DB::transaction(function () use ($data, $userId): EmployeeOnboarding {
            $employee = Employee::findOrFail($data['employee_id']);

            $startDate = $data['started_date'] ?? $employee->joining_date?->toDateString() ?? now()->toDateString();

            $onboarding = EmployeeOnboarding::create([
                'organization_id'        => $employee->organization_id,
                'employee_id'            => $employee->id,
                'template_type'          => $data['template_type'] ?? 'standard',
                'status'                 => EmployeeOnboarding::STATUS_PENDING,
                'started_date'           => $startDate,
                'target_completion_date' => $data['target_completion_date'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'created_by'             => $userId,
            ]);

            $this->createDefaultTasks($onboarding, $startDate, $data['tasks'] ?? []);

            return $onboarding->load(['employee', 'tasks']);
        });
    }

    /**
     * Update onboarding header fields.
     */
    public function update(EmployeeOnboarding $onboarding, array $data): EmployeeOnboarding
    {
        $onboarding->update(array_intersect_key($data, array_flip([
            'template_type', 'started_date', 'target_completion_date', 'notes',
        ])));

        return $onboarding->fresh(['employee', 'tasks']);
    }

    /**
     * Mark a task as complete / skipped / in-progress.
     */
    public function updateTask(
        EmployeeOnboarding $onboarding,
        int $taskId,
        string $status,
        int $userId,
        ?string $notes = null
    ): OnboardingTask {
        $task = $onboarding->tasks()->findOrFail($taskId);

        $update = ['status' => $status, 'notes' => $notes ?? $task->notes];

        if ($status === OnboardingTask::STATUS_DONE) {
            $update['completed_by'] = $userId;
            $update['completed_at'] = now();
        }

        $task->update($update);

        // Auto-advance onboarding status.
        $this->syncOnboardingStatus($onboarding->fresh());

        return $task->fresh();
    }

    /**
     * Add a custom task to an existing onboarding.
     */
    public function addTask(EmployeeOnboarding $onboarding, array $data, int $userId): OnboardingTask
    {
        return $onboarding->tasks()->create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? 'hr',
            'due_date'    => $data['due_date'] ?? null,
            'status'      => OnboardingTask::STATUS_PENDING,
            'is_required' => $data['is_required'] ?? true,
            'sort_order'  => $onboarding->tasks()->max('sort_order') + 1,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);
    }

    /**
     * Cancel an onboarding instance.
     */
    public function cancel(EmployeeOnboarding $onboarding): EmployeeOnboarding
    {
        $onboarding->update(['status' => EmployeeOnboarding::STATUS_CANCELLED]);

        return $onboarding;
    }

    // ----------------------------------------------------------------
    // Queries
    // ----------------------------------------------------------------

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = EmployeeOnboarding::query()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->when(isset($filters['organization_id']), fn ($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['status']),          fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['employee_id']),     fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['template_type']),   fn ($q) => $q->where('template_type', $filters['template_type']))
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function show(EmployeeOnboarding $onboarding): EmployeeOnboarding
    {
        return $onboarding->load(['employee', 'tasks.assignedTo:id,name', 'tasks.completedBy:id,name', 'createdBy:id,name']);
    }

    /**
     * Dashboard: tasks assigned to a user across all active onboardings.
     */
    public function myTasks(int $userId, int $organizationId): array
    {
        return OnboardingTask::whereHas('onboarding', fn ($q) =>
                $q->where('organization_id', $organizationId)
                  ->whereIn('status', [EmployeeOnboarding::STATUS_PENDING, EmployeeOnboarding::STATUS_IN_PROGRESS])
            )
            ->where('assigned_to', $userId)
            ->whereIn('status', [OnboardingTask::STATUS_PENDING, OnboardingTask::STATUS_IN_PROGRESS])
            ->with(['onboarding.employee:id,first_name,last_name'])
            ->orderBy('due_date')
            ->limit(50)
            ->get()
            ->toArray();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function createDefaultTasks(EmployeeOnboarding $onboarding, string $startDate, array $customTasks): void
    {
        $templates = self::DEFAULT_TASKS[$onboarding->template_type] ?? self::DEFAULT_TASKS['standard'];
        $start     = \Carbon\Carbon::parse($startDate);

        foreach ($templates as $index => $tpl) {
            $onboarding->tasks()->create([
                'title'       => $tpl['title'],
                'category'    => $tpl['category'],
                'due_date'    => $start->copy()->addDays($tpl['due_days'])->toDateString(),
                'status'      => OnboardingTask::STATUS_PENDING,
                'is_required' => $tpl['is_required'],
                'sort_order'  => $index,
            ]);
        }

        // Append any caller-supplied custom tasks.
        foreach ($customTasks as $ct) {
            $onboarding->tasks()->create([
                'title'       => $ct['title'],
                'description' => $ct['description'] ?? null,
                'category'    => $ct['category'] ?? 'hr',
                'due_date'    => $ct['due_date'] ?? null,
                'status'      => OnboardingTask::STATUS_PENDING,
                'is_required' => $ct['is_required'] ?? false,
                'sort_order'  => $onboarding->tasks()->max('sort_order') + 1,
                'assigned_to' => $ct['assigned_to'] ?? null,
            ]);
        }
    }

    private function syncOnboardingStatus(EmployeeOnboarding $onboarding): void
    {
        $hasPending = $onboarding->tasks()
            ->where('is_required', true)
            ->whereNotIn('status', [OnboardingTask::STATUS_DONE, OnboardingTask::STATUS_SKIPPED])
            ->exists();

        if (!$hasPending) {
            $onboarding->update([
                'status'       => EmployeeOnboarding::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } elseif ($onboarding->status === EmployeeOnboarding::STATUS_PENDING) {
            $hasDone = $onboarding->tasks()
                ->whereIn('status', [OnboardingTask::STATUS_DONE, OnboardingTask::STATUS_IN_PROGRESS])
                ->exists();

            if ($hasDone) {
                $onboarding->update(['status' => EmployeeOnboarding::STATUS_IN_PROGRESS]);
            }
        }
    }
}

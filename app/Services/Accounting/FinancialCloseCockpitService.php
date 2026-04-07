<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FinancialCloseTask;
use App\Models\Accounting\FinancialClosePeriod;
use App\Models\Accounting\FinancialCloseTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FinancialCloseCockpitService
{
    /**
     * Create a financial close period and instantiate tasks from the template (if given).
     */
    public function createPeriod(array $data): FinancialClosePeriod
    {
        return DB::transaction(function () use ($data): FinancialClosePeriod {
            $period = FinancialClosePeriod::create(array_merge($data, [
                'status'    => FinancialClosePeriod::STATUS_OPEN,
                'opened_at' => now(),
            ]));

            $templateId = $data['financial_close_template_id'] ?? null;

            if ($templateId) {
                $template = FinancialCloseTemplate::with('tasks')->find($templateId);

                if ($template) {
                    foreach ($template->tasks as $tmplTask) {
                        FinancialCloseTask::create([
                            'financial_close_period_id' => $period->id,
                            'template_task_id'          => $tmplTask->id,
                            'task_name'                 => $tmplTask->task_name,
                            'description'               => $tmplTask->description,
                            'task_type'                 => $tmplTask->task_type,
                            'status'                    => FinancialCloseTask::STATUS_PENDING,
                            'sort_order'                => $tmplTask->sort_order,
                        ]);
                    }
                }
            }

            return $period->load('tasks');
        });
    }

    /**
     * Start a task (transition pending → in_progress).
     */
    public function startTask(FinancialCloseTask $task, int $userId): void
    {
        if ($task->status !== FinancialCloseTask::STATUS_PENDING) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be started (current status: {$task->status})."
            );
        }

        if (!$this->canStartTask($task)) {
            throw new RuntimeException(
                "Task [{$task->task_name}] has unresolved dependencies."
            );
        }

        $task->update([
            'status'     => FinancialCloseTask::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'assigned_to' => $userId,
        ]);
    }

    /**
     * Complete a task (transition in_progress → completed).
     */
    public function completeTask(FinancialCloseTask $task, int $userId, string $notes = ''): void
    {
        if (!in_array($task->status, [
            FinancialCloseTask::STATUS_IN_PROGRESS,
            FinancialCloseTask::STATUS_PENDING,
        ], true)) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be completed (current status: {$task->status})."
            );
        }

        $task->update([
            'status'       => FinancialCloseTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'notes'        => $notes ?: $task->notes,
        ]);
    }

    /**
     * Check whether all dependency tasks are completed.
     */
    public function canStartTask(FinancialCloseTask $task): bool
    {
        return $task->dependencies()
            ->where('status', '!=', FinancialCloseTask::STATUS_COMPLETED)
            ->doesntExist();
    }

    /**
     * Skip a task (must be pending or blocked).
     */
    public function skipTask(FinancialCloseTask $task, int $userId, string $reason = ''): void
    {
        if (!in_array($task->status, [
            FinancialCloseTask::STATUS_PENDING,
            FinancialCloseTask::STATUS_BLOCKED,
        ], true)) {
            throw new InvalidArgumentException(
                "Task [{$task->task_name}] cannot be skipped (current status: {$task->status})."
            );
        }

        $task->update([
            'status'       => FinancialCloseTask::STATUS_SKIPPED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'notes'        => $reason ?: $task->notes,
        ]);
    }

    /**
     * Assign a task to a user.
     */
    public function assignTask(FinancialCloseTask $task, int $assigneeId): void
    {
        if ($task->status === FinancialCloseTask::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Cannot reassign a completed task.');
        }

        $task->update(['assigned_to' => $assigneeId]);
    }

    /**
     * Record a formal sign-off on a closed period (CFO / controller approval).
     */
    public function signOff(FinancialClosePeriod $period, int $userId, string $notes = ''): void
    {
        if ($period->status !== FinancialClosePeriod::STATUS_CLOSED) {
            throw new InvalidArgumentException('Period must be closed before it can be signed off.');
        }

        if ($period->signed_off_by !== null) {
            throw new InvalidArgumentException('Period has already been signed off.');
        }

        $period->update([
            'signed_off_by'   => $userId,
            'signed_off_at'   => now(),
            'sign_off_notes'  => $notes,
        ]);
    }

    /**
     * Get progress summary for a close period.
     *
     * @return array{total: int, pending: int, in_progress: int, completed: int, blocked: int, skipped: int, percent_complete: float}
     */
    public function getPeriodProgress(FinancialClosePeriod $period): array
    {
        $counts = $period->tasks()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total     = (int) array_sum($counts);
        $completed = (int) ($counts[FinancialCloseTask::STATUS_COMPLETED] ?? 0);
        $percent   = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'total'            => $total,
            'pending'          => (int) ($counts[FinancialCloseTask::STATUS_PENDING] ?? 0),
            'in_progress'      => (int) ($counts[FinancialCloseTask::STATUS_IN_PROGRESS] ?? 0),
            'completed'        => $completed,
            'blocked'          => (int) ($counts[FinancialCloseTask::STATUS_BLOCKED] ?? 0),
            'skipped'          => (int) ($counts[FinancialCloseTask::STATUS_SKIPPED] ?? 0),
            'percent_complete' => $percent,
        ];
    }

    /**
     * Close a financial period (all tasks must be completed or skipped).
     */
    public function closePeriod(FinancialClosePeriod $period, int $userId): void
    {
        if ($period->status === FinancialClosePeriod::STATUS_CLOSED) {
            throw new InvalidArgumentException('Period is already closed.');
        }

        $blockerCount = $period->tasks()
            ->whereIn('status', [
                FinancialCloseTask::STATUS_PENDING,
                FinancialCloseTask::STATUS_IN_PROGRESS,
                FinancialCloseTask::STATUS_BLOCKED,
            ])
            ->count();

        if ($blockerCount > 0) {
            throw new RuntimeException(
                "{$blockerCount} task(s) are still open. Complete or skip them before closing the period."
            );
        }

        $period->update([
            'status'    => FinancialClosePeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);
    }
}

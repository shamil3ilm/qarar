<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar\CalendarTask;
use App\Models\Calendar\CalendarTaskComment;
use Illuminate\Support\Facades\DB;

class CalendarTaskService
{
    /**
     * Create a new task.
     */
    public function create(array $data, int $userId): CalendarTask
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['status'] = $data['status'] ?? CalendarTask::STATUS_PENDING;
            $data['priority'] = $data['priority'] ?? CalendarTask::PRIORITY_MEDIUM;
            $data['progress'] = $data['progress'] ?? 0;

            $task = CalendarTask::create($data);

            return $task->load(['assignee', 'creator']);
        });
    }

    /**
     * Update an existing task.
     */
    public function update(CalendarTask $task, array $data): CalendarTask
    {
        return DB::transaction(function () use ($task, $data) {
            if ($task->isCompleted() || $task->isCancelled()) {
                throw new \InvalidArgumentException('Completed or cancelled tasks cannot be updated.');
            }

            $task->update($data);

            return $task->fresh(['assignee', 'creator']);
        });
    }

    /**
     * Mark a task as completed.
     */
    public function complete(CalendarTask $task): CalendarTask
    {
        return DB::transaction(function () use ($task) {
            if (!$task->canBeCompleted()) {
                throw new \InvalidArgumentException('Task cannot be completed in its current state.');
            }

            $task->markAsCompleted();

            return $task->fresh(['assignee', 'creator']);
        });
    }

    /**
     * Assign a task to a user.
     */
    public function assign(CalendarTask $task, int $userId): CalendarTask
    {
        return DB::transaction(function () use ($task, $userId) {
            $task->update(['assigned_to' => $userId]);

            return $task->fresh(['assignee', 'creator']);
        });
    }

    /**
     * Add a comment to a task.
     */
    public function addComment(CalendarTask $task, array $data, int $userId): CalendarTaskComment
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            $data['task_id'] = $task->id;
            $data['user_id'] = $data['user_id'] ?? $userId;

            return CalendarTaskComment::create($data);
        });
    }
}

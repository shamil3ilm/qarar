<?php

declare(strict_types=1);

namespace App\Services\TaskBoard;

use App\Models\TaskBoard\BoardTask;
use App\Models\TaskBoard\BoardTaskComment;
use App\Models\TaskBoard\TaskActivity;
use App\Models\TaskBoard\TaskAttachment;
use App\Models\TaskBoard\TaskBoard;
use App\Models\TaskBoard\TaskBoardColumn;
use App\Models\TaskBoard\TaskChecklist;
use App\Models\TaskBoard\TaskChecklistItem;
use App\Models\TaskBoard\TaskDependency;
use App\Models\TaskBoard\TaskLabel;
use App\Models\TaskBoard\TaskLabelAssignment;
use App\Models\TaskBoard\TaskTimeEntry;
use App\Models\TaskBoard\TaskWatcher;
use Illuminate\Support\Facades\DB;

class BoardTaskService
{
    /**
     * Create a new board task.
     */
    public function create(TaskBoard $board, array $data, int $userId): BoardTask
    {
        return DB::transaction(function () use ($board, $data, $userId) {
            $data['board_id'] = $board->id;
            $data['reporter_id'] = $data['reporter_id'] ?? $userId;
            $data['status'] = $data['status'] ?? BoardTask::STATUS_OPEN;
            $data['priority'] = $data['priority'] ?? BoardTask::PRIORITY_MEDIUM;
            $data['task_type'] = $data['task_type'] ?? BoardTask::TYPE_TASK;

            // Set column to default if not provided
            if (empty($data['column_id'])) {
                $defaultColumn = $board->getDefaultColumn();
                $data['column_id'] = $defaultColumn?->id;
            }

            // Auto-generate task number
            if (empty($data['task_number'])) {
                $count = $board->tasks()->withTrashed()->count() + 1;
                $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $board->name), 0, 4));
                $data['task_number'] = $prefix . '-' . $count;
            }

            // Set position
            if (!isset($data['position'])) {
                $data['position'] = BoardTask::where('column_id', $data['column_id'])->max('position') + 1;
            }

            $task = BoardTask::create($data);

            // Log activity
            TaskActivity::log($task->id, TaskActivity::TYPE_CREATED);

            // Auto-watch reporter
            TaskWatcher::create([
                'task_id' => $task->id,
                'user_id' => $task->reporter_id,
            ]);

            return $task->load(['board', 'column', 'assignee', 'reporter', 'labels']);
        });
    }

    /**
     * Update a board task.
     */
    public function update(BoardTask $task, array $data): BoardTask
    {
        return DB::transaction(function () use ($task, $data) {
            $oldValues = $task->only(array_keys($data));

            $task->update($data);

            // Log field changes
            foreach ($data as $field => $newValue) {
                $oldValue = $oldValues[$field] ?? null;
                if ($oldValue != $newValue) {
                    $activityType = match ($field) {
                        'status' => TaskActivity::TYPE_STATUS_CHANGED,
                        'assignee_id' => TaskActivity::TYPE_ASSIGNED,
                        'priority' => TaskActivity::TYPE_PRIORITY_CHANGED,
                        'due_date' => TaskActivity::TYPE_DUE_DATE_CHANGED,
                        default => null,
                    };

                    if ($activityType) {
                        TaskActivity::log(
                            $task->id,
                            $activityType,
                            $field,
                            (string) $oldValue,
                            (string) $newValue
                        );
                    }
                }
            }

            return $task->fresh(['board', 'column', 'assignee', 'reporter', 'labels']);
        });
    }

    /**
     * Move a task to a different column and/or position.
     */
    public function move(BoardTask $task, int $columnId, ?int $position = null): BoardTask
    {
        return DB::transaction(function () use ($task, $columnId, $position) {
            $oldColumnId = $task->column_id;
            $column = TaskBoardColumn::findOrFail($columnId);

            // Check WIP limit
            if ($column->hasWipLimit() && $column->isAtWipLimit() && $columnId !== $oldColumnId) {
                throw new \InvalidArgumentException(
                    "Column '{$column->name}' has reached its WIP limit of {$column->wip_limit}."
                );
            }

            $updateData = ['column_id' => $columnId];

            if ($position !== null) {
                $updateData['position'] = $position;
            }

            // Auto-update status based on column
            if ($column->isDoneColumn()) {
                $updateData['status'] = BoardTask::STATUS_COMPLETED;
                $updateData['completed_at'] = now();
            } elseif ($oldColumnId !== $columnId) {
                // If moving from done column, reopen
                $oldColumn = TaskBoardColumn::find($oldColumnId);
                if ($oldColumn && $oldColumn->isDoneColumn()) {
                    $updateData['status'] = BoardTask::STATUS_IN_PROGRESS;
                    $updateData['completed_at'] = null;
                }
            }

            $task->update($updateData);

            if ($oldColumnId !== $columnId) {
                $oldColumn = TaskBoardColumn::find($oldColumnId);
                TaskActivity::log(
                    $task->id,
                    TaskActivity::TYPE_MOVED,
                    'column_id',
                    $oldColumn?->name,
                    $column->name
                );
            }

            return $task->fresh(['board', 'column', 'assignee', 'reporter']);
        });
    }

    /**
     * Assign a task to a user.
     */
    public function assign(BoardTask $task, ?int $userId): BoardTask
    {
        return DB::transaction(function () use ($task, $userId) {
            $oldAssignee = $task->assignee_id;

            $task->update(['assignee_id' => $userId]);

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_ASSIGNED,
                'assignee_id',
                (string) $oldAssignee,
                (string) $userId
            );

            // Auto-watch assignee
            if ($userId && !$task->watchers()->where('user_id', $userId)->exists()) {
                TaskWatcher::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                ]);
            }

            return $task->fresh(['assignee', 'reporter']);
        });
    }

    /**
     * Add a label to a task.
     */
    public function addLabel(BoardTask $task, int $labelId): BoardTask
    {
        return DB::transaction(function () use ($task, $labelId) {
            $label = TaskLabel::findOrFail($labelId);

            $existing = TaskLabelAssignment::where('task_id', $task->id)
                ->where('label_id', $labelId)
                ->exists();

            if ($existing) {
                throw new \InvalidArgumentException('Label is already assigned to this task.');
            }

            TaskLabelAssignment::create([
                'task_id' => $task->id,
                'label_id' => $labelId,
            ]);

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_LABEL_ADDED,
                'label',
                null,
                $label->name
            );

            return $task->fresh(['labels']);
        });
    }

    /**
     * Add a checklist to a task.
     */
    public function addChecklist(BoardTask $task, array $data): TaskChecklist
    {
        return DB::transaction(function () use ($task, $data) {
            $maxPosition = $task->checklists()->max('position') ?? -1;

            $checklist = TaskChecklist::create([
                'task_id' => $task->id,
                'title' => $data['title'],
                'position' => $data['position'] ?? ($maxPosition + 1),
            ]);

            // Create items if provided
            if (!empty($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    TaskChecklistItem::create([
                        'checklist_id' => $checklist->id,
                        'content' => $item['content'],
                        'position' => $index,
                        'assignee_id' => $item['assignee_id'] ?? null,
                        'due_date' => $item['due_date'] ?? null,
                    ]);
                }
            }

            TaskActivity::log($task->id, TaskActivity::TYPE_CHECKLIST_ADDED, null, null, $data['title']);

            return $checklist->load('items');
        });
    }

    /**
     * Toggle a checklist item's completion status.
     */
    public function toggleChecklistItem(TaskChecklistItem $item): TaskChecklistItem
    {
        return DB::transaction(function () use ($item) {
            $item->toggle();

            return $item->fresh();
        });
    }

    /**
     * Log time against a task.
     */
    public function logTime(BoardTask $task, array $data, int $userId): TaskTimeEntry
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            $data['task_id'] = $task->id;
            $data['user_id'] = $data['user_id'] ?? $userId;

            // Calculate duration if started_at and ended_at are provided
            if (!empty($data['started_at']) && !empty($data['ended_at']) && empty($data['duration_minutes'])) {
                $start = \Carbon\Carbon::parse($data['started_at']);
                $end = \Carbon\Carbon::parse($data['ended_at']);
                $data['duration_minutes'] = (int) $start->diffInMinutes($end);
            }

            $entry = TaskTimeEntry::create($data);

            // Update actual hours on task
            $totalMinutes = $task->timeEntries()->sum('duration_minutes');
            $task->update(['actual_hours' => (int) ceil($totalMinutes / 60)]);

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_TIME_LOGGED,
                'time',
                null,
                $entry->getDurationFormatted(),
                ['time_entry_id' => $entry->id]
            );

            return $entry;
        });
    }

    /**
     * Add a dependency between tasks.
     */
    public function addDependency(BoardTask $task, int $dependsOnTaskId, string $type = 'finish_to_start'): TaskDependency
    {
        return DB::transaction(function () use ($task, $dependsOnTaskId, $type) {
            if ($task->id === $dependsOnTaskId) {
                throw new \InvalidArgumentException('A task cannot depend on itself.');
            }

            $existing = TaskDependency::where('task_id', $task->id)
                ->where('depends_on_task_id', $dependsOnTaskId)
                ->exists();

            if ($existing) {
                throw new \InvalidArgumentException('This dependency already exists.');
            }

            $dependency = TaskDependency::create([
                'task_id' => $task->id,
                'depends_on_task_id' => $dependsOnTaskId,
                'dependency_type' => $type,
            ]);

            // Check if the dependency is blocking and mark the task
            if ($dependency->isBlocking()) {
                $task->update([
                    'is_blocked' => true,
                    'blocked_reason' => 'Blocked by dependency: ' . $dependency->dependsOnTask->task_number,
                ]);
            }

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_DEPENDENCY_ADDED,
                null,
                null,
                (string) $dependsOnTaskId,
                ['dependency_type' => $type]
            );

            return $dependency->load('dependsOnTask');
        });
    }

    /**
     * Add an attachment to a task.
     */
    public function addAttachment(BoardTask $task, array $data, int $userId): TaskAttachment
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            $data['task_id'] = $task->id;
            $data['uploaded_by'] = $data['uploaded_by'] ?? $userId;

            $attachment = TaskAttachment::create($data);

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_ATTACHMENT_ADDED,
                null,
                null,
                $data['file_name'],
                ['attachment_id' => $attachment->id]
            );

            return $attachment->load('uploader');
        });
    }

    /**
     * Add a comment to a task.
     */
    public function addComment(BoardTask $task, array $data, int $userId): BoardTaskComment
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            $data['task_id'] = $task->id;
            $data['user_id'] = $data['user_id'] ?? $userId;

            $comment = BoardTaskComment::create($data);

            TaskActivity::log(
                $task->id,
                TaskActivity::TYPE_COMMENTED,
                null,
                null,
                null,
                ['comment_id' => $comment->id]
            );

            return $comment->load('user');
        });
    }

    /**
     * Watch/unwatch a task.
     */
    public function watch(BoardTask $task, int $userId): bool
    {
        return DB::transaction(function () use ($task, $userId) {

            $existing = $task->watchers()->where('user_id', $userId)->first();

            if ($existing) {
                $existing->delete();
                return false; // Unwatched
            }

            TaskWatcher::create([
                'task_id' => $task->id,
                'user_id' => $userId,
            ]);

            return true; // Watching
        });
    }
}

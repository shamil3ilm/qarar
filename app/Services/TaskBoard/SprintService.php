<?php

declare(strict_types=1);

namespace App\Services\TaskBoard;

use App\Models\TaskBoard\BoardTask;
use App\Models\TaskBoard\TaskBoard;
use App\Models\TaskBoard\TaskSprint;
use App\Models\TaskBoard\TaskSprintItem;
use Illuminate\Support\Facades\DB;

class SprintService
{
    /**
     * Create a new sprint.
     */
    public function create(TaskBoard $board, array $data): TaskSprint
    {
        return DB::transaction(function () use ($board, $data) {
            if (!$board->isScrum()) {
                throw new \InvalidArgumentException('Sprints can only be created for Scrum boards.');
            }

            // Check no active sprint already exists
            $activeSprint = $board->sprints()->active()->first();
            if ($activeSprint && ($data['status'] ?? TaskSprint::STATUS_PLANNED) === TaskSprint::STATUS_ACTIVE) {
                throw new \InvalidArgumentException('There is already an active sprint for this board.');
            }

            $data['board_id'] = $board->id;
            $data['status'] = $data['status'] ?? TaskSprint::STATUS_PLANNED;

            $sprint = TaskSprint::create($data);

            // Add tasks if provided
            if (!empty($data['task_ids'])) {
                foreach ($data['task_ids'] as $taskId) {
                    $task = BoardTask::findOrFail($taskId);
                    TaskSprintItem::create([
                        'sprint_id' => $sprint->id,
                        'task_id' => $taskId,
                        'points' => $task->story_points,
                        'added_at' => now(),
                    ]);
                }

                $sprint->recalculatePoints();
            }

            return $sprint->load('items.task');
        });
    }

    /**
     * Start a sprint.
     */
    public function start(TaskSprint $sprint): TaskSprint
    {
        return DB::transaction(function () use ($sprint) {
            if (!$sprint->canBeStarted()) {
                throw new \InvalidArgumentException('Sprint cannot be started in its current state.');
            }

            // Check no other active sprint on the same board
            $activeSprint = $sprint->board->sprints()
                ->active()
                ->where('id', '!=', $sprint->id)
                ->first();

            if ($activeSprint) {
                throw new \InvalidArgumentException(
                    "Sprint '{$activeSprint->name}' is already active. Complete it before starting a new one."
                );
            }

            $sprint->update([
                'status' => TaskSprint::STATUS_ACTIVE,
                'started_at' => now(),
            ]);

            $sprint->recalculatePoints();

            return $sprint->fresh('items.task');
        });
    }

    /**
     * Complete a sprint.
     */
    public function complete(TaskSprint $sprint): TaskSprint
    {
        return DB::transaction(function () use ($sprint) {
            if (!$sprint->canBeCompleted()) {
                throw new \InvalidArgumentException('Sprint cannot be completed in its current state.');
            }

            $sprint->recalculatePoints();

            $sprint->update([
                'status' => TaskSprint::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $sprint->fresh('items.task');
        });
    }

    /**
     * Add tasks to a sprint.
     */
    public function addTasks(TaskSprint $sprint, array $taskIds): TaskSprint
    {
        return DB::transaction(function () use ($sprint, $taskIds) {
            foreach ($taskIds as $taskId) {
                $existing = TaskSprintItem::where('sprint_id', $sprint->id)
                    ->where('task_id', $taskId)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $task = BoardTask::findOrFail($taskId);

                TaskSprintItem::create([
                    'sprint_id' => $sprint->id,
                    'task_id' => $taskId,
                    'points' => $task->story_points,
                    'added_at' => now(),
                ]);
            }

            $sprint->recalculatePoints();

            return $sprint->fresh('items.task');
        });
    }

    /**
     * Remove tasks from a sprint.
     */
    public function removeTasks(TaskSprint $sprint, array $taskIds): TaskSprint
    {
        return DB::transaction(function () use ($sprint, $taskIds) {
            TaskSprintItem::where('sprint_id', $sprint->id)
                ->whereIn('task_id', $taskIds)
                ->delete();

            $sprint->recalculatePoints();

            return $sprint->fresh('items.task');
        });
    }

    /**
     * Get burndown chart data for a sprint.
     */
    public function getBurndown(TaskSprint $sprint): array
    {
        $startDate = $sprint->started_at ?? $sprint->start_date;
        $endDate = $sprint->completed_at ?? $sprint->end_date;
        $today = now();

        $totalPoints = $sprint->total_points;
        $durationDays = (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;

        // Ideal burndown line
        $idealBurndown = [];
        $pointsPerDay = $durationDays > 1 ? $totalPoints / ($durationDays - 1) : $totalPoints;

        for ($i = 0; $i < $durationDays; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $idealBurndown[] = [
                'date' => $date,
                'points' => max(0, round($totalPoints - ($pointsPerDay * $i), 1)),
            ];
        }

        // Actual burndown - track completed points per day
        $sprintItems = $sprint->items()->with('task')->get();
        $actualBurndown = [];
        $remainingPoints = $totalPoints;

        $completedTasksByDate = [];
        foreach ($sprintItems as $item) {
            if ($item->task && $item->task->completed_at) {
                $completedDate = $item->task->completed_at->toDateString();
                if (!isset($completedTasksByDate[$completedDate])) {
                    $completedTasksByDate[$completedDate] = 0;
                }
                $completedTasksByDate[$completedDate] += $item->points ?? 0;
            }
        }

        $lastDate = min($today, $endDate);
        $iterDays = (int) $startDate->copy()->startOfDay()->diffInDays($lastDate->copy()->startOfDay()) + 1;

        for ($i = 0; $i < $iterDays; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $completedOnDay = $completedTasksByDate[$date] ?? 0;
            $remainingPoints -= $completedOnDay;

            $actualBurndown[] = [
                'date' => $date,
                'points' => max(0, $remainingPoints),
            ];
        }

        return [
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'status' => $sprint->status,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_points' => $totalPoints,
                'completed_points' => $sprint->completed_points,
                'remaining_points' => $totalPoints - $sprint->completed_points,
                'completion_percentage' => $sprint->getCompletionPercentage(),
                'velocity' => $sprint->getVelocity(),
                'remaining_days' => $sprint->getRemainingDays(),
            ],
            'ideal_burndown' => $idealBurndown,
            'actual_burndown' => $actualBurndown,
        ];
    }
}

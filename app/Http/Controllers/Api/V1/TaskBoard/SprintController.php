<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TaskBoard;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard\TaskSprint;
use App\Models\TaskBoard\TaskSprintItem;
use App\Services\TaskBoard\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function __construct(private SprintService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $sprints = TaskSprint::orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($sprints);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'board_id' => 'required|integer|exists:task_boards,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'goal' => 'nullable|string',
        ]);

        $sprint = TaskSprint::create(array_merge($request->all(), [
            'created_by' => auth()->id(),
        ]));
        return $this->created($sprint);
    }

    public function show(TaskSprint $sprint): JsonResponse
    {
        return $this->success($sprint->load('items.task'));
    }

    public function update(Request $request, TaskSprint $sprint): JsonResponse
    {
        $sprint->update($request->all());
        return $this->success($sprint->fresh());
    }

    public function destroy(TaskSprint $sprint): JsonResponse
    {
        $sprint->delete();
        return $this->success(['message' => 'Sprint deleted']);
    }

    public function start(TaskSprint $sprint): JsonResponse
    {
        if ($sprint->status !== 'planned') {
            return $this->error('Sprint can only be started from planned status', 'INVALID_STATUS', 400);
        }

        $sprint->update(['status' => 'active', 'started_at' => now()]);
        return $this->success($sprint->fresh());
    }

    public function complete(TaskSprint $sprint): JsonResponse
    {
        if ($sprint->status !== 'active') {
            return $this->error('Sprint can only be completed from active status', 'INVALID_STATUS', 400);
        }

        $sprint->update(['status' => 'completed', 'completed_at' => now()]);
        return $this->success($sprint->fresh());
    }

    public function addTasks(Request $request, TaskSprint $sprint): JsonResponse
    {
        $request->validate([
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer',
        ]);

        foreach ($request->input('task_ids', []) as $taskId) {
            TaskSprintItem::firstOrCreate([
                'sprint_id' => $sprint->id,
                'task_id' => $taskId,
            ]);
        }
        return $this->success($sprint->fresh()->load('items'));
    }

    public function removeTasks(Request $request, TaskSprint $sprint): JsonResponse
    {
        TaskSprintItem::where('sprint_id', $sprint->id)
            ->whereIn('task_id', $request->input('task_ids', []))
            ->delete();
        return $this->success(['message' => 'Tasks removed from sprint']);
    }

    public function burndownChart(TaskSprint $sprint): JsonResponse
    {
        $totalTasks = $sprint->items()->count();
        $completedTasks = $sprint->items()
            ->whereHas('task', fn ($q) => $q->where('status', 'done'))
            ->count();

        return $this->success([
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'remaining' => $totalTasks - $completedTasks,
            'progress_percent' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
        ]);
    }
}

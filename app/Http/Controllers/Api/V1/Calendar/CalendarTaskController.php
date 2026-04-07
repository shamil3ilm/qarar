<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\CalendarTask;
use App\Services\Calendar\CalendarTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarTaskController extends Controller
{
    public function __construct(
        private CalendarTaskService $taskService
    ) {}

    /**
     * List tasks with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CalendarTask::with(['assignee', 'creator'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn($q, $priority) => $q->withPriority($priority))
            ->when($request->assigned_to, fn($q, $id) => $q->assignedTo((int) $id))
            ->when($request->created_by, fn($q, $id) => $q->createdBy((int) $id))
            ->when($request->boolean('open'), fn($q) => $q->open())
            ->when($request->boolean('overdue'), fn($q) => $q->overdue())
            ->when($request->boolean('due_today'), fn($q) => $q->dueToday())
            ->when($request->boolean('top_level'), fn($q) => $q->topLevel())
            ->when($request->due_start && $request->due_end, function ($q) use ($request) {
                $q->dueBetween($request->due_start, $request->due_end);
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['title', 'due_date', 'priority', 'status', 'created_at', 'updated_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        if ($request->per_page) {
            return $this->paginated($query->paginate((int) $request->per_page));
        }

        return $this->success($query->get());
    }

    /**
     * Store a new task.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'taskable_type' => 'nullable|string',
            'taskable_id' => 'nullable|integer',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'tags' => 'nullable|array',
            'checklist' => 'nullable|array',
            'is_recurring' => 'nullable|boolean',
        ]);

        $task = $this->taskService->create($validated, auth()->id());

        return $this->created($task);
    }

    /**
     * Show a specific task.
     */
    public function show(CalendarTask $task): JsonResponse
    {
        return $this->success(
            $task->load(['assignee', 'creator', 'subtasks', 'comments.user', 'parentTask'])
        );
    }

    /**
     * Update a task.
     */
    public function update(Request $request, CalendarTask $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'progress' => 'nullable|integer|min:0|max:100',
            'tags' => 'nullable|array',
            'checklist' => 'nullable|array',
        ]);

        $task = $this->taskService->update($task, $validated);

        return $this->success($task, 'Task updated successfully.');
    }

    /**
     * Delete a task.
     */
    public function destroy(CalendarTask $task): JsonResponse
    {
        $task->comments()->delete();
        $task->subtasks()->delete();
        $task->delete();

        return $this->success(null, 'Task deleted successfully.');
    }

    /**
     * Mark a task as completed.
     */
    public function complete(CalendarTask $task): JsonResponse
    {
        $task = $this->taskService->complete($task);

        return $this->success($task, 'Task completed successfully.');
    }

    /**
     * Add a comment to a task.
     */
    public function addComment(Request $request, CalendarTask $task): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment = $this->taskService->addComment($task, $validated, auth()->id());

        return $this->created($comment->load('user'));
    }

    /**
     * List comments for a task.
     */
    public function comments(CalendarTask $task): JsonResponse
    {
        $comments = $task->comments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($comments);
    }
}

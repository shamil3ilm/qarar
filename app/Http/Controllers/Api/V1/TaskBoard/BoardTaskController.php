<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TaskBoard;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard\BoardTask;
use App\Models\TaskBoard\BoardTaskComment;
use App\Models\TaskBoard\TaskBoard;
use App\Models\TaskBoard\TaskChecklist;
use App\Models\TaskBoard\TaskTimeEntry;
use App\Services\TaskBoard\BoardTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardTaskController extends Controller
{
    public function __construct(private BoardTaskService $service)
    {
    }

    public function index(Request $request, TaskBoard $board): JsonResponse
    {
        $tasks = BoardTask::where('board_id', $board->id)
            ->with('assignee', 'column', 'labels')
            ->when($request->input('column_id'), fn ($q, $c) => $q->where('column_id', $c))
            ->orderBy('position')
            ->paginate($request->input('per_page', 50));
        return $this->paginated($tasks);
    }

    public function store(Request $request, TaskBoard $board): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'column_id' => 'required|integer|exists:task_board_columns,id',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assignee_id' => 'nullable|integer|exists:users,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $orgId = auth()->user()->organization_id ?? request()->header('X-Organization-ID');
        $taskCount = \App\Models\TaskBoard\BoardTask::where('board_id', $board->id)->withTrashed()->count();
        $taskNumber = 'TASK-' . ($taskCount + 1);

        $task = BoardTask::create(array_merge($request->all(), [
            'board_id' => $board->id,
            'organization_id' => $orgId,
            'reporter_id' => auth()->id(),
            'created_by' => auth()->id(),
            'task_number' => $taskNumber,
        ]));
        return $this->created($task);
    }

    public function show(TaskBoard $board, BoardTask $task): JsonResponse
    {
        return $this->success($task->load('assignee', 'column', 'comments', 'checklists.items', 'timeEntries', 'attachments', 'labels'));
    }

    public function update(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $task->update($request->all());
        return $this->success($task->fresh());
    }

    public function destroy(TaskBoard $board, BoardTask $task): JsonResponse
    {
        $task->delete();
        return $this->success(['message' => 'Task deleted']);
    }

    public function move(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $request->validate([
            'column_id' => 'nullable|integer|exists:task_board_columns,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $task->update([
            'column_id' => $request->input('column_id', $task->column_id),
            'position' => $request->input('position', $task->position),
        ]);
        return $this->success($task->fresh());
    }

    public function assign(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $request->validate([
            'assignee_id' => 'required|integer|exists:users,id',
        ]);

        $task->update(['assignee_id' => $request->input('assignee_id')]);
        return $this->success($task->fresh()->load('assignee'));
    }

    public function addComment(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment = BoardTaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);
        return $this->created($comment);
    }

    public function addChecklist(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => $validated['title'],
        ]);
        return $this->created($checklist);
    }

    public function addTimeEntry(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $validated = $request->validate([
            'minutes' => 'nullable|integer|min:1|required_without_all:duration_minutes,started_at',
            'duration_minutes' => 'nullable|integer|min:1|required_without_all:minutes,started_at',
            'description' => 'nullable|string',
            'started_at' => 'nullable|date|required_without_all:minutes,duration_minutes',
            'ended_at' => 'nullable|date',
            'is_billable' => 'nullable|boolean',
        ]);

        $durationMinutes = $validated['duration_minutes'] ?? $validated['minutes'] ?? null;
        $startedAt = $validated['started_at'] ?? now();

        $entry = TaskTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'description' => $validated['description'] ?? null,
            'started_at' => $startedAt,
            'ended_at' => $validated['ended_at'] ?? null,
            'duration_minutes' => $durationMinutes,
            'is_billable' => $validated['is_billable'] ?? true,
        ]);
        return $this->created($entry);
    }

    public function addAttachment(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:10240',
            ]);

            $file = $request->file('file');
            $path = $file->store("task-attachments/{$task->id}", 'local');

            $attachment = $task->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        } else {
            $request->validate([
                'file_name' => 'required|string',
                'file_path' => 'required|string',
            ]);

            $attachment = $task->attachments()->create([
                'file_name' => $request->input('file_name'),
                'file_path' => $request->input('file_path'),
                'file_type' => $request->input('file_type'),
                'file_size' => $request->input('file_size'),
                'uploaded_by' => auth()->id(),
            ]);
        }

        return $this->created($attachment);
    }

    public function addLabel(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $task->labels()->attach($request->input('label_id'));
        return $this->success(['message' => 'Label added']);
    }

    public function addWatcher(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $task->watchers()->create(['user_id' => $request->input('user_id')]);
        return $this->success(['message' => 'Watcher added']);
    }

    public function addDependency(Request $request, TaskBoard $board, BoardTask $task): JsonResponse
    {
        $request->validate([
            'depends_on_task_id' => [
                'required',
                'integer',
                'exists:tasks,id',
                function ($attribute, $value, $fail) use ($task) {
                    if ((int) $value === $task->id) {
                        $fail('A task cannot depend on itself.');
                    }
                },
            ],
            'dependency_type' => 'nullable|string|in:blocks,finish_to_start,start_to_start,finish_to_finish',
        ]);

        $task->dependencies()->create([
            'depends_on_task_id' => $request->input('depends_on_task_id'),
            'dependency_type' => $request->input('dependency_type', 'blocks'),
        ]);
        return $this->success(['message' => 'Dependency added']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TaskBoard;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard\TaskBoard;
use App\Models\TaskBoard\TaskBoardColumn;
use App\Models\TaskBoard\TaskBoardTemplate;
use App\Services\TaskBoard\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskBoardController extends Controller
{
    public function __construct(
        private TaskBoardService $boardService
    ) {
    }

    /**
     * List boards with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaskBoard::with(['creator', 'members.user'])
            ->notTemplates()
            ->when($request->board_type, fn($q, $type) => $q->ofType($type))
            ->when($request->boolean('archived'), fn($q) => $q->archived(), fn($q) => $q->active())
            ->when($request->visibility, fn($q, $v) => $q->withVisibility($v))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        if ($request->per_page) {
            return $this->paginated($query->paginate((int) $request->per_page));
        }

        return $this->success($query->get());
    }

    /**
     * Store a new board.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'board_type' => 'nullable|in:kanban,scrum,simple',
            'visibility' => 'nullable|in:private,team,organization',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'columns' => 'nullable|array',
            'columns.*.name' => 'required_with:columns|string|max:255',
            'columns.*.color' => 'nullable|string|max:7',
            'columns.*.wip_limit' => 'nullable|integer|min:1',
            'columns.*.is_done_column' => 'nullable|boolean',
        ]);

        $board = $this->boardService->create($validated, auth()->id());

        return $this->created($board);
    }

    /**
     * Create a board from a template.
     */
    public function createFromTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:task_board_templates,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'nullable|in:private,team,organization',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $template = TaskBoardTemplate::findOrFail($validated['template_id']);
        unset($validated['template_id']);

        $board = $this->boardService->createFromTemplate($template, $validated, auth()->id());

        return $this->created($board);
    }

    /**
     * Show a specific board.
     */
    public function show(TaskBoard $board): JsonResponse
    {
        return $this->success(
            $board->load([
                'creator',
                'members.user',
                'columns.tasks.assignee',
                'columns.tasks.labels',
                'labels',
                'sprints' => fn($q) => $q->orderBy('start_date', 'desc'),
            ])
        );
    }

    /**
     * Update a board.
     */
    public function update(Request $request, TaskBoard $board): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'nullable|in:private,team,organization',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'is_archived' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $board->update($validated);

        return $this->success($board->fresh(['creator', 'members.user', 'columns']), 'Board updated successfully.');
    }

    /**
     * Delete a board.
     */
    public function destroy(TaskBoard $board): JsonResponse
    {
        $board->delete();

        return $this->success(null, 'Board deleted successfully.');
    }

    /**
     * Add a member to the board.
     */
    public function addMember(Request $request, TaskBoard $board): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:admin,member,viewer',
        ]);

        $member = $this->boardService->addMember(
            $board,
            $validated['user_id'],
            $validated['role'] ?? 'member'
        );

        return $this->created($member->load('user'));
    }

    /**
     * Remove a member from the board.
     */
    public function removeMember(TaskBoard $board, int $member): JsonResponse
    {
        $this->boardService->removeMember($board, $member);

        return $this->success(null, 'Member removed successfully.');
    }

    /**
     * List members of the board.
     */
    public function members(TaskBoard $board): JsonResponse
    {
        $members = $board->members()->with('user')->get();

        return $this->success($members);
    }

    /**
     * Add a column to the board.
     */
    public function addColumn(Request $request, TaskBoard $board): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'position' => 'nullable|integer|min:0',
            'wip_limit' => 'nullable|integer|min:1',
            'is_done_column' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $column = $this->boardService->addColumn($board, $validated);

        return $this->created($column);
    }

    /**
     * Update a column.
     */
    public function updateColumn(Request $request, TaskBoard $board, TaskBoardColumn $column): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:7',
            'wip_limit' => 'nullable|integer|min:1',
            'is_done_column' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $column->update($validated);

        return $this->success($column->fresh(), 'Column updated successfully.');
    }

    /**
     * Delete a column.
     */
    public function removeColumn(TaskBoard $board, TaskBoardColumn $column): JsonResponse
    {
        if ($column->tasks()->count() > 0) {
            return $this->error(
                'Cannot delete column with existing tasks. Move tasks first.',
                'COLUMN_HAS_TASKS',
                422
            );
        }

        $column->delete();

        return $this->success(null, 'Column deleted successfully.');
    }

    /**
     * Reorder columns.
     */
    public function reorderColumns(Request $request, TaskBoard $board): JsonResponse
    {
        $validated = $request->validate([
            'column_order' => 'required|array',
            'column_order.*' => 'integer|exists:task_board_columns,id',
        ]);

        $this->boardService->reorderColumns($board, $validated['column_order']);

        return $this->success(
            $board->fresh('columns'),
            'Columns reordered successfully.'
        );
    }

    /**
     * List available board templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = TaskBoardTemplate::available()
            ->when($request->board_type, fn($q, $type) => $q->ofType($type))
            ->get();

        return $this->success($templates);
    }

    /**
     * List labels for the board.
     */
    public function labels(TaskBoard $board): JsonResponse
    {
        return $this->success($board->labels);
    }

    /**
     * Add a label to the board.
     */
    public function addLabel(Request $request, TaskBoard $board): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
        ]);

        $validated['board_id'] = $board->id;

        $label = \App\Models\TaskBoard\TaskLabel::create($validated);

        return $this->created($label);
    }
}

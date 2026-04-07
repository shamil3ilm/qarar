<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\SchedulingBoard;
use App\Models\Manufacturing\SchedulingOperation;
use App\Services\Manufacturing\DetailedSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DetailedSchedulingController extends Controller
{
    public function __construct(
        private readonly DetailedSchedulingService $service,
    ) {}

    // ── Boards ────────────────────────────────────────────────────────────────

    /**
     * List scheduling boards.
     */
    public function boards(Request $request): JsonResponse
    {
        $query = SchedulingBoard::withCount('operations')
            ->orderBy('name');

        $boards = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($boards);
    }

    /**
     * Create a new scheduling board.
     */
    public function storeBoard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'horizon_days'    => 'nullable|integer|min:1|max:365',
            'work_center_ids' => 'nullable|array',
            'work_center_ids.*' => ['integer', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
        ]);

        $board = SchedulingBoard::create($validated);

        return $this->created($board);
    }

    /**
     * Return Gantt-ready data for a board in a date window.
     */
    public function boardData(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $board = SchedulingBoard::find($id);

        if ($board === null) {
            return $this->notFound('Scheduling board not found.');
        }

        $data = $this->service->getBoardData($id, $validated['date_from'], $validated['date_to']);

        return $this->success($data);
    }

    // ── Operations ────────────────────────────────────────────────────────────

    /**
     * List scheduling operations with filtering.
     */
    public function operations(Request $request): JsonResponse
    {
        $query = SchedulingOperation::with(['workCenter', 'workOrder', 'processOrder'])
            ->when($request->work_center_id, fn($q, $v) => $q->forWorkCenter((int) $v))
            ->when($request->board_id, fn($q, $v) => $q->forBoard((int) $v))
            ->when($request->work_order_id, fn($q, $v) => $q->where('work_order_id', $v))
            ->when($request->date_from && $request->date_to, fn($q) => $q->between($request->date_from, $request->date_to))
            ->orderBy('work_center_id')
            ->orderBy('planned_start');

        $operations = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($operations);
    }

    /**
     * Create a scheduling operation manually.
     */
    public function storeOperation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scheduling_board_id' => 'nullable|exists:scheduling_boards,id',
            'work_order_id'       => 'nullable|exists:work_orders,id',
            'process_order_id'    => 'nullable|exists:process_orders,id',
            'work_center_id'      => ['required', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'operation_number'    => 'required|integer|min:1',
            'description'         => 'required|string|max:255',
            'planned_start'       => 'required|date',
            'planned_finish'      => 'required|date|after:planned_start',
            'duration_minutes'    => 'required|integer|min:1',
            'setup_minutes'       => 'nullable|integer|min:0',
            'teardown_minutes'    => 'nullable|integer|min:0',
            'priority'            => 'nullable|integer|min:1|max:100',
            'is_pinned'           => 'nullable|boolean',
            'is_fixed'            => 'nullable|boolean',
            'sequence_number'     => 'nullable|integer|min:1',
        ]);

        $operation = SchedulingOperation::create(array_merge(
            $validated,
            ['organization_id' => auth()->user()->organization_id]
        ));

        return $this->created($operation->load(['workCenter', 'workOrder', 'processOrder']));
    }

    /**
     * Update a scheduling operation.
     */
    public function updateOperation(Request $request, int $id): JsonResponse
    {
        $operation = SchedulingOperation::find($id);

        if ($operation === null) {
            return $this->notFound('Operation not found.');
        }

        $validated = $request->validate([
            'scheduling_board_id' => 'nullable|exists:scheduling_boards,id',
            'work_center_id'      => ['sometimes', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'operation_number'    => 'sometimes|integer|min:1',
            'description'         => 'sometimes|string|max:255',
            'planned_start'       => 'sometimes|date',
            'planned_finish'      => 'sometimes|date',
            'duration_minutes'    => 'sometimes|integer|min:1',
            'setup_minutes'       => 'nullable|integer|min:0',
            'teardown_minutes'    => 'nullable|integer|min:0',
            'priority'            => 'nullable|integer|min:1|max:100',
            'is_pinned'           => 'nullable|boolean',
            'is_fixed'            => 'nullable|boolean',
            'sequence_number'     => 'nullable|integer|min:1',
        ]);

        $operation->update($validated);

        return $this->success($operation->fresh(['workCenter', 'workOrder', 'processOrder']));
    }

    /**
     * Reschedule an operation to a new start time (cascades to successors).
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $operation = SchedulingOperation::find($id);

        if ($operation === null) {
            return $this->notFound('Operation not found.');
        }

        $validated = $request->validate([
            'new_start' => 'required|date',
        ]);

        $this->service->rescheduleOperation($operation, $validated['new_start']);

        return $this->success($operation->fresh(['workCenter']), 'Operation rescheduled.');
    }

    /**
     * Suggest an optimised sequence for a work center on a given date.
     */
    public function optimize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => ['required', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'date'           => 'required|date',
        ]);

        $sequence = $this->service->optimizeSequence((int) $validated['work_center_id'], $validated['date']);

        return $this->success($sequence);
    }

    /**
     * Detect scheduling conflicts on a work center in a date range.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => ['required', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
        ]);

        $conflicts = $this->service->detectConflicts(
            (int) $validated['work_center_id'],
            $validated['date_from'],
            $validated['date_to']
        );

        return $this->success([
            'work_center_id' => $validated['work_center_id'],
            'conflict_count' => count($conflicts),
            'conflicts'      => $conflicts,
        ]);
    }
}

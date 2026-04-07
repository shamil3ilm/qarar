<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\OmPositionTask;
use App\Models\HR\OmTask;
use App\Services\HR\OmTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OmTaskController extends Controller
{
    public function __construct(
        private readonly OmTaskService $omTaskService
    ) {}

    /**
     * List all OM tasks.
     */
    public function index(Request $request): JsonResponse
    {
        $tasks = $this->omTaskService->list($request->only([
            'is_active',
            'task_type',
            'search',
            'per_page',
        ]));

        return $this->paginated($tasks);
    }

    /**
     * Create a new OM task.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'task_code'   => [
                'required',
                'string',
                'max:20',
                Rule::unique('om_tasks', 'task_code')->where('organization_id', $orgId),
            ],
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type'   => ['nullable', Rule::in([
                OmTask::TYPE_FUNCTION,
                OmTask::TYPE_ACTIVITY,
                OmTask::TYPE_RESPONSIBILITY,
            ])],
            'is_active'   => 'nullable|boolean',
        ]);

        $task = $this->omTaskService->create($validated);

        return $this->created($task, 'OM task created successfully.');
    }

    /**
     * Show a specific OM task.
     */
    public function show(OmTask $omTask): JsonResponse
    {
        return $this->success($omTask->load('positionAssignments.position'));
    }

    /**
     * Update an OM task.
     */
    public function update(Request $request, OmTask $omTask): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'task_code'   => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('om_tasks', 'task_code')->where('organization_id', $orgId)->ignore($omTask->id),
            ],
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'task_type'   => ['nullable', Rule::in([
                OmTask::TYPE_FUNCTION,
                OmTask::TYPE_ACTIVITY,
                OmTask::TYPE_RESPONSIBILITY,
            ])],
            'is_active'   => 'nullable|boolean',
        ]);

        $task = $this->omTaskService->update($omTask, $validated);

        return $this->success($task, 'OM task updated successfully.');
    }

    /**
     * Delete an OM task.
     */
    public function destroy(OmTask $omTask): JsonResponse
    {
        $this->omTaskService->delete($omTask);

        return $this->success(null, 'OM task deleted successfully.');
    }

    /**
     * Assign an OM task to a position.
     */
    public function assignToPosition(Request $request, OmTask $omTask): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'position_id'          => ['required', Rule::exists('positions', 'id')->where('organization_id', $orgId)],
            'responsibility_level' => ['nullable', Rule::in([
                OmPositionTask::RESPONSIBILITY_LEVEL_PRIMARY,
                OmPositionTask::RESPONSIBILITY_LEVEL_SECONDARY,
                OmPositionTask::RESPONSIBILITY_LEVEL_ADDITIONAL,
            ])],
            'valid_from' => 'nullable|date',
            'valid_to'   => 'nullable|date|after_or_equal:valid_from',
        ]);

        try {
            $assignment = $this->omTaskService->assignToPosition($omTask, $validated['position_id'], $validated);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'OM_TASK_ASSIGN_FAILED', 422);
        }

        return $this->created($assignment->load('task', 'position'), 'Task assigned to position successfully.');
    }

    /**
     * Remove a task from a position.
     */
    public function removeFromPosition(OmTask $omTask, OmPositionTask $assignment): JsonResponse
    {
        $this->omTaskService->removeFromPosition($assignment);

        return $this->success(null, 'Task assignment removed successfully.');
    }

    /**
     * List all tasks assigned to a position.
     */
    public function positionTasks(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'position_id' => ['required', Rule::exists('positions', 'id')->where('organization_id', $orgId)],
        ]);

        $tasks = $this->omTaskService->getTasksForPosition((int) $validated['position_id']);

        return $this->success($tasks);
    }
}

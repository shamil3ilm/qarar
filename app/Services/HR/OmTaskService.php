<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\OmPositionTask;
use App\Models\HR\OmTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OmTaskService
{
    /**
     * Paginated list of OM tasks for the authenticated organisation.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $orgId = auth()->user()->organization_id;

        return OmTask::where('organization_id', $orgId)
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['task_type']), fn($q) => $q->where('task_type', $filters['task_type']))
            ->when(isset($filters['search']), fn($q, $s) => $q->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('task_code', 'like', '%' . $filters['search'] . '%');
            }))
            ->orderBy('task_code')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a new OM task.
     */
    public function create(array $data): OmTask
    {
        return OmTask::create([
            'organization_id' => auth()->user()->organization_id,
            'task_code'       => $data['task_code'],
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'task_type'       => $data['task_type'] ?? OmTask::TYPE_FUNCTION,
            'is_active'       => $data['is_active'] ?? true,
            'created_by'      => auth()->id(),
        ]);
    }

    /**
     * Update an existing OM task.
     */
    public function update(OmTask $task, array $data): OmTask
    {
        $task->update(array_intersect_key($data, array_flip([
            'task_code',
            'name',
            'description',
            'task_type',
            'is_active',
        ])));

        return $task->fresh();
    }

    /**
     * Soft-delete an OM task.
     */
    public function delete(OmTask $task): void
    {
        $task->delete();
    }

    /**
     * Assign a task to a position.
     */
    public function assignToPosition(OmTask $task, int $positionId, array $data): OmPositionTask
    {
        return OmPositionTask::create([
            'organization_id'    => auth()->user()->organization_id,
            'position_id'        => $positionId,
            'task_id'            => $task->id,
            'responsibility_level' => $data['responsibility_level'] ?? OmPositionTask::RESPONSIBILITY_LEVEL_PRIMARY,
            'valid_from'         => $data['valid_from'] ?? null,
            'valid_to'           => $data['valid_to'] ?? null,
        ]);
    }

    /**
     * Remove a position-task assignment.
     */
    public function removeFromPosition(OmPositionTask $assignment): void
    {
        $assignment->delete();
    }

    /**
     * Get all tasks for a given position.
     */
    public function getTasksForPosition(int $positionId): Collection
    {
        return OmPositionTask::with('task')
            ->where('organization_id', auth()->user()->organization_id)
            ->where('position_id', $positionId)
            ->get();
    }
}

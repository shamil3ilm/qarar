<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\HR\DepartmentResource;
use App\Models\HR\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * List departments with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::with(['parent', 'manager'])
            ->withCount('activeEmployees')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('root_only', false), fn ($q) => $q->root())
            ->when($request->parent_id, fn ($q, $parentId) => $q->where('parent_id', $parentId))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'code', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $departments = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($departments, DepartmentResource::class);
    }

    /**
     * Create a new department.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $organizationId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('departments', 'code')
                    ->where('organization_id', $organizationId),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'manager_id' => 'nullable|integer|exists:users,id',
            'cost_center_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $department = Department::create([
            'organization_id' => $organizationId,
            ...$validated,
        ]);

        $department->load(['parent', 'manager']);

        return $this->created(new DepartmentResource($department), 'Department created successfully.');
    }

    /**
     * Show a specific department.
     */
    public function show(Department $department): JsonResponse
    {
        $department->load(['parent', 'children', 'manager'])
            ->loadCount(['employees', 'activeEmployees']);

        return $this->success(new DepartmentResource($department));
    }

    /**
     * Update a department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($department->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('departments', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($department->id),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                Rule::notIn([$department->id]),
            ],
            'manager_id' => 'nullable|integer|exists:users,id',
            'cost_center_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $department->update($validated);
        $department->load(['parent', 'manager']);

        return $this->success(new DepartmentResource($department), 'Department updated successfully.');
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->count() > 0) {
            return $this->error(
                'Cannot delete department with assigned employees. Reassign employees first.',
                'VALIDATION_ERROR',
                422
            );
        }

        if ($department->children()->count() > 0) {
            return $this->error(
                'Cannot delete department with sub-departments. Remove or reassign sub-departments first.',
                'VALIDATION_ERROR',
                422
            );
        }

        $department->delete();

        return $this->success(null, 'Department deleted successfully.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\HR\DesignationResource;
use App\Models\HR\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    /**
     * List designations with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Designation::query()
            ->withCount('activeEmployees')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->level, fn ($q, $level) => $q->byLevel((int) $level))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $designations = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($designations, DesignationResource::class);
    }

    /**
     * Create a new designation.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('designations', 'name')
                    ->where('organization_id', $organizationId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('designations', 'code')
                    ->where('organization_id', $organizationId),
            ],
            'description' => 'nullable|string|max:500',
            'level' => 'nullable|integer|min:1|max:20',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
        ]);

        $designation = Designation::create([
            'organization_id' => $organizationId,
            ...$validated,
        ]);

        return $this->created(new DesignationResource($designation), 'Designation created successfully.');
    }

    /**
     * Show a specific designation.
     */
    public function show(Designation $designation): JsonResponse
    {
        $designation->loadCount(['employees', 'activeEmployees']);

        return $this->success(new DesignationResource($designation));
    }

    /**
     * Update a designation.
     */
    public function update(Request $request, Designation $designation): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('designations', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($designation->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('designations', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($designation->id),
            ],
            'description' => 'nullable|string|max:500',
            'level' => 'nullable|integer|min:1|max:20',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
        ]);

        $designation->update($validated);

        return $this->success(new DesignationResource($designation), 'Designation updated successfully.');
    }

    /**
     * Delete a designation.
     */
    public function destroy(Designation $designation): JsonResponse
    {
        if ($designation->employees()->count() > 0) {
            return $this->error(
                'Cannot delete designation with assigned employees. Reassign employees first.',
                'VALIDATION_ERROR',
                422
            );
        }

        $designation->delete();

        return $this->success(null, 'Designation deleted successfully.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * List roles with permissions, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions')
            ->where('organization_id', auth('api')->user()->organization_id)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_system'), fn ($q) => $q->where('is_system', $request->boolean('is_system')))
            ->orderBy('name');

        $roles = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($roles);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->where('organization_id', $organizationId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('roles', 'slug')
                    ->where('organization_id', $organizationId),
            ],
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        // Prevent privilege escalation: non-super-admins cannot assign permissions they don't hold
        if (!empty($validated['permission_ids']) && !$request->user()->is_super_admin) {
            $userPermissions = $request->user()->getAllPermissions(); // string[] of slugs
            foreach ($validated['permission_ids'] as $permId) {
                $perm = \App\Models\Core\Permission::find($permId);
                if ($perm && !in_array($perm->slug, $userPermissions, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                            'permission_ids' => ["Cannot assign permission '{$perm->name}' that you do not have."],
                        ]);
                }
            }
        }

        $role = Role::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (!empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load('permissions');

        return $this->created($this->formatRole($role), 'Role created successfully.');
    }

    /**
     * Show a role with permissions.
     */
    public function show(Role $role): JsonResponse
    {
        abort_unless(auth('api')->user()->hasPermission('core.roles.view'), 403, 'Permission denied.');

        $role->load(['permissions', 'users']);

        $data = $this->formatRole($role);
        $data['users_count'] = $role->users->count();

        return $this->success($data);
    }

    /**
     * Update a role and sync permissions.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless(auth('api')->user()->hasPermission('core.roles.edit'), 403, 'Permission denied.');

        if ($role->is_system) {
            return $this->error(
                'System roles cannot be modified.',
                'VALIDATION_ERROR',
                422
            );
        }

        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($role->id),
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('roles', 'slug')
                    ->where('organization_id', $organizationId)
                    ->ignore($role->id),
            ],
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        // Prevent privilege escalation: non-super-admins cannot assign permissions they don't hold
        if (!empty($validated['permission_ids']) && !$request->user()->is_super_admin) {
            $userPermissions = $request->user()->getAllPermissions(); // string[] of slugs
            foreach ($validated['permission_ids'] as $permId) {
                $perm = \App\Models\Core\Permission::find($permId);
                if ($perm && !in_array($perm->slug, $userPermissions, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                            'permission_ids' => ["Cannot assign permission '{$perm->name}' that you do not have."],
                        ]);
                }
            }
        }

        $role->update(
            collect($validated)->only(['name', 'slug', 'description'])->reject(fn ($v) => $v === null)->toArray()
        );

        if (array_key_exists('permission_ids', $validated)) {
            $role->permissions()->sync($validated['permission_ids'] ?? []);
        }

        $role->load('permissions');

        return $this->success($this->formatRole($role), 'Role updated successfully.');
    }

    /**
     * Delete a role (only if no users are assigned).
     */
    public function destroy(Role $role): JsonResponse
    {
        abort_unless(auth('api')->user()->hasPermission('core.roles.delete'), 403, 'Permission denied.');

        if ($role->is_system) {
            return $this->error(
                'System roles cannot be deleted.',
                'VALIDATION_ERROR',
                422
            );
        }

        if ($role->users()->count() > 0) {
            return $this->error(
                'Cannot delete role with assigned users. Reassign users first.',
                'VALIDATION_ERROR',
                422
            );
        }

        $role->permissions()->detach();
        $role->delete();

        return $this->success(null, 'Role deleted successfully.');
    }

    /**
     * Format role data for API response.
     */
    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'module' => $p->module,
                ])
                : [],
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }
}

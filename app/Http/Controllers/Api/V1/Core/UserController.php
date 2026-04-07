<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List users in the organization with search/filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $query = User::where('organization_id', $organizationId)
            ->with(['branches', 'roles'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($q, $role) => $q->whereHas('roles', fn ($rq) => $rq->where('slug', $role)))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->branch_id, fn ($q, $branchId) => $q->whereHas('branches', fn ($bq) => $bq->where('branches.id', $branchId)))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'email', 'created_at', 'updated_at', 'is_active'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $users = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($users, UserResource::class);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
            'default_branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $user = DB::transaction(function () use ($validated, $organizationId) {
            $user = User::create([
                'organization_id' => $organizationId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'preferred_language' => $validated['preferred_language'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['role_ids'])) {
                $user->roles()->sync($validated['role_ids']);
            }

            if (!empty($validated['branch_ids'])) {
                $defaultBranchId = $validated['default_branch_id'] ?? $validated['branch_ids'][0] ?? null;
                $branchData = [];
                foreach ($validated['branch_ids'] as $branchId) {
                    $branchData[$branchId] = ['is_default' => $branchId === $defaultBranchId];
                }
                $user->branches()->sync($branchData);
            }

            return $user->load(['branches', 'roles']);
        });

        return $this->created(new UserResource($user), 'User created successfully.');
    }

    /**
     * Show a single user with roles and branches.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        $user->load(['branches', 'roles.permissions', 'organization']);

        return $this->success(new UserResource($user));
    }

    /**
     * Update user details, roles, and branches.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
            'default_branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $user = DB::transaction(function () use ($user, $validated) {
            $updateData = collect($validated)
                ->only(['name', 'email', 'phone', 'preferred_language', 'timezone', 'is_active'])
                ->filter(fn ($value, $key) => $value !== null || in_array($key, ['phone', 'preferred_language', 'timezone']))
                ->toArray();

            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            if (array_key_exists('role_ids', $validated)) {
                $user->roles()->sync($validated['role_ids'] ?? []);
            }

            if (array_key_exists('branch_ids', $validated)) {
                $branchIds = $validated['branch_ids'] ?? [];
                $defaultBranchId = $validated['default_branch_id'] ?? ($branchIds[0] ?? null);
                $branchData = [];
                foreach ($branchIds as $branchId) {
                    $branchData[$branchId] = ['is_default' => $branchId === $defaultBranchId];
                }
                $user->branches()->sync($branchData);
            }

            return $user->fresh(['branches', 'roles']);
        });

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    /**
     * Soft delete / deactivate a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorizeOrganizationAccess($user);

        if ($user->id === auth()->id()) {
            return $this->error(
                'You cannot delete your own account.',
                'VALIDATION_ERROR',
                422
            );
        }

        $user->update(['is_active' => false]);
        $user->delete();

        return $this->success(null, 'User deactivated successfully.');
    }

    /**
     * Ensure the target user belongs to the same organization as the authenticated user.
     */
    private function authorizeOrganizationAccess(User $user): void
    {
        if ($user->organization_id !== auth()->user()->organization_id) {
            abort(403, 'You do not have access to this user.');
        }
    }
}

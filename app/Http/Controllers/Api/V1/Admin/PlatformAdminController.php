<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PlatformAdmin;
use App\Models\Core\Organization;
use App\Models\User;
use App\Services\Admin\PlatformAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    public function __construct(private PlatformAdminService $service) {}

    public function index(Request $request): JsonResponse
    {
        $admins = PlatformAdmin::orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($admins);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:platform_admins,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|string|max:30',
        ]);

        $data = $request->except('password_confirmation');
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        $admin = PlatformAdmin::create($data);
        return $this->created($admin);
    }

    public function show(PlatformAdmin $admin): JsonResponse
    {
        return $this->success($admin);
    }

    public function update(Request $request, PlatformAdmin $admin): JsonResponse
    {
        $admin->update($request->except('password_confirmation'));
        return $this->success($admin->fresh());
    }

    public function destroy(PlatformAdmin $admin): JsonResponse
    {
        $admin->delete();
        return $this->success(['message' => 'Admin deleted']);
    }

    public function listOrganizations(Request $request): JsonResponse
    {
        $orgs = Organization::when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('users')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($orgs);
    }

    public function showOrganization(Organization $organization): JsonResponse
    {
        return $this->success($organization->loadCount('users', 'branches'));
    }

    public function suspendOrganization(Request $request, Organization $organization): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $organization->update(['status' => 'suspended']);
        return $this->success($organization->fresh());
    }

    public function activateOrganization(Organization $organization): JsonResponse
    {
        $organization->update(['status' => 'active']);
        return $this->success($organization->fresh());
    }

    public function listUsers(Request $request): JsonResponse
    {
        if (!auth()->user()?->is_super_admin) {
            abort(403, 'Forbidden: super-admin access required.');
        }

        $users = User::with('organization')
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($users);
    }
}

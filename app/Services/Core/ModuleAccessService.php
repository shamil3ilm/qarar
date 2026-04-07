<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ModuleAccessLog;
use App\Models\Core\ModuleDefinition;
use App\Models\Core\OrganizationModuleAccess;
use App\Models\Core\RoleMenuItem;
use App\Models\Core\RoleModulePermission;
use App\Models\Core\UserModuleOverride;
use Illuminate\Support\Facades\DB;

class ModuleAccessService
{
    public function getModules(): mixed
    {
        return ModuleDefinition::where('is_active', true)->orderBy('display_order')->get();
    }

    public function getOrganizationModules(int $organizationId): mixed
    {
        return OrganizationModuleAccess::where('organization_id', $organizationId)
            ->where('is_enabled', true)
            ->with('module')
            ->get();
    }

    public function enableModule(int $organizationId, int $moduleId, int $userId): OrganizationModuleAccess
    {
        return OrganizationModuleAccess::updateOrCreate(
            ['organization_id' => $organizationId, 'module_id' => $moduleId],
            ['is_enabled' => true, 'enabled_at' => now(), 'disabled_at' => null, 'enabled_by' => $userId]
        );
    }

    public function disableModule(int $organizationId, int $moduleId): OrganizationModuleAccess
    {
        $access = OrganizationModuleAccess::where('organization_id', $organizationId)
            ->where('module_id', $moduleId)
            ->firstOrFail();

        $access->update(['is_enabled' => false, 'disabled_at' => now()]);

        return $access->fresh();
    }

    public function getRolePermissions(int $roleId): mixed
    {
        return RoleModulePermission::where('role_id', $roleId)->with('module')->get();
    }

    public function setRolePermission(int $roleId, int $moduleId, array $permissions): RoleModulePermission
    {
        return RoleModulePermission::updateOrCreate(
            ['role_id' => $roleId, 'module_id' => $moduleId],
            $permissions
        );
    }

    public function getUserEffectivePermissions(int $userId, int $moduleId): array
    {
        $override = UserModuleOverride::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->first();

        if ($override && $override->override_type === 'revoke') {
            return ['can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false];
        }

        $user = \App\Models\User::with('roles')->findOrFail($userId);
        $roleIds = $user->roles->pluck('id');

        $rolePermission = RoleModulePermission::where('module_id', $moduleId)
            ->whereIn('role_id', $roleIds)
            ->orderByDesc('can_view')
            ->first();

        $base = $rolePermission ? $rolePermission->toArray() : [];

        if ($override) {
            foreach (['can_view', 'can_create', 'can_edit', 'can_delete', 'can_export', 'can_import', 'can_approve'] as $field) {
                if ($override->$field !== null) {
                    $base[$field] = $override->$field;
                }
            }
        }

        return $base;
    }

    public function checkAccess(int $userId, int $moduleId, string $action): bool
    {
        $permissions = $this->getUserEffectivePermissions($userId, $moduleId);
        $field = "can_{$action}";

        return $permissions[$field] ?? false;
    }

    public function logAccess(int $orgId, int $userId, int $moduleId, string $action, bool $allowed, ?string $entityType = null, ?int $entityId = null): void
    {
        ModuleAccessLog::create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'module_id' => $moduleId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'was_allowed' => $allowed,
            'ip_address' => request()->ip(),
            'accessed_at' => now(),
        ]);
    }

    public function getMenuItems(int $roleId): mixed
    {
        return RoleMenuItem::where('role_id', $roleId)
            ->where('is_visible', true)
            ->with('module')
            ->orderBy('position')
            ->get();
    }
}

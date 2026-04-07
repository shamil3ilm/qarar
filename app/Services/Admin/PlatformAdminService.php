<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\PlatformAdmin;
use App\Models\Admin\PlatformAdminActivity;
use App\Models\Admin\PlatformAdminSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformAdminService
{
    /**
     * Create a new platform admin.
     */
    public function create(array $data): PlatformAdmin
    {
        return DB::transaction(function () use ($data) {
            $admin = PlatformAdmin::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? PlatformAdmin::ROLE_ADMIN,
                'avatar' => $data['avatar'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'permissions' => $data['permissions'] ?? null,
            ]);

            $this->logActivity($admin, 'admin_created', 'PlatformAdmin', $admin->id, [
                'new_values' => ['name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role],
            ]);

            return $admin;
        });
    }

    /**
     * Update an admin's role and permissions.
     */
    public function updateRole(PlatformAdmin $admin, string $role, ?array $permissions = null): PlatformAdmin
    {
        return DB::transaction(function () use ($admin, $role, $permissions) {
            $oldRole = $admin->role;

            $admin->update([
                'role' => $role,
                'permissions' => $permissions ?? $admin->permissions,
            ]);

            $this->logActivity($admin, 'role_updated', 'PlatformAdmin', $admin->id, [
                'old_values' => ['role' => $oldRole],
                'new_values' => ['role' => $role],
            ]);

            return $admin->fresh();
        });
    }

    /**
     * Authenticate a platform admin and create a session.
     */
    public function authenticate(string $email, string $password, array $sessionData = []): ?array
    {
        $admin = PlatformAdmin::where('email', $email)->active()->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            return null;
        }

        return DB::transaction(function () use ($admin, $sessionData) {
            $admin->update([
                'last_login_at' => now(),
                'last_login_ip' => $sessionData['ip_address'] ?? null,
            ]);

            $session = PlatformAdminSession::create([
                'admin_id' => $admin->id,
                'session_token' => Str::random(64),
                'ip_address' => $sessionData['ip_address'] ?? '0.0.0.0',
                'user_agent' => $sessionData['user_agent'] ?? '',
                'device_type' => $sessionData['device_type'] ?? null,
                'browser' => $sessionData['browser'] ?? null,
                'os' => $sessionData['os'] ?? null,
                'last_activity_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            $this->logActivity($admin, 'login', null, null, [
                'metadata' => ['ip_address' => $sessionData['ip_address'] ?? null],
            ]);

            return [
                'admin' => $admin,
                'session' => $session,
            ];
        });
    }

    /**
     * Log an admin activity.
     */
    public function logActivity(
        PlatformAdmin $admin,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $extras = []
    ): PlatformAdminActivity {
        return PlatformAdminActivity::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'organization_id' => $extras['organization_id'] ?? null,
            'old_values' => $extras['old_values'] ?? null,
            'new_values' => $extras['new_values'] ?? null,
            'metadata' => $extras['metadata'] ?? null,
            'ip_address' => $extras['ip_address'] ?? request()->ip(),
            'user_agent' => $extras['user_agent'] ?? request()->userAgent(),
        ]);
    }

    /**
     * Manage admin sessions - revoke, list active, cleanup expired.
     */
    public function manageSessions(PlatformAdmin $admin, string $action, ?int $sessionId = null): array
    {
        return match ($action) {
            'revoke' => $this->revokeSession($admin, $sessionId),
            'revoke_all' => $this->revokeAllSessions($admin),
            'list' => $this->listSessions($admin),
            'cleanup' => $this->cleanupExpiredSessions($admin),
            default => throw new \InvalidArgumentException("Unknown session action: {$action}"),
        };
    }

    private function revokeSession(PlatformAdmin $admin, ?int $sessionId): array
    {
        $session = $admin->sessions()->findOrFail($sessionId);
        $session->revoke();

        return ['revoked' => 1];
    }

    private function revokeAllSessions(PlatformAdmin $admin): array
    {
        $count = $admin->sessions()->active()->update(['is_revoked' => true]);

        return ['revoked' => $count];
    }

    private function listSessions(PlatformAdmin $admin): array
    {
        return [
            'sessions' => $admin->sessions()->active()->orderByDesc('last_activity_at')->get()->toArray(),
        ];
    }

    private function cleanupExpiredSessions(PlatformAdmin $admin): array
    {
        $count = $admin->sessions()
            ->where('expires_at', '<', now())
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        return ['cleaned' => $count];
    }
}

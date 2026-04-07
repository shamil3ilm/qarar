<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\User;
use App\Services\Auth\TokenBlacklistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserLifecycleService
{
    public function __construct(
        private TokenBlacklistService $tokenBlacklistService
    ) {}

    /**
     * Deactivate a user (soft disable, keeps data).
     */
    public function deactivate(User $user, string $reason, int $deactivatedBy): void
    {
        $this->validateNotLastAdmin($user);

        DB::transaction(function () use ($user, $reason, $deactivatedBy) {
            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $deactivatedBy,
                'deactivation_reason' => $reason,
            ]);

            // Invalidate all active sessions
            $this->invalidateAllSessions($user);

            // Audit log
            DB::table('activity_logs')->insert([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $user->organization_id,
                'user_id' => auth()->id(),
                'action' => 'user_deactivated',
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
                'description' => "User {$user->email} deactivated",
                'module' => 'core',
                'severity' => 'warning',
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Reactivate a deactivated user.
     */
    public function reactivate(User $user): void
    {
        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
            'deactivation_reason' => null,
        ]);
    }

    /**
     * Soft delete a user with proper cleanup.
     */
    public function softDelete(User $user, string $reason, int $deletedBy): void
    {
        $this->validateNotLastAdmin($user);

        DB::transaction(function () use ($user, $reason, $deletedBy) {
            // First deactivate
            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $deletedBy,
                'deactivation_reason' => $reason,
            ]);

            // Invalidate all sessions
            $this->invalidateAllSessions($user);

            // Audit log
            DB::table('activity_logs')->insert([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $user->organization_id,
                'user_id' => auth()->id(),
                'action' => 'user_deleted',
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
                'description' => "User {$user->email} soft-deleted",
                'module' => 'core',
                'severity' => 'warning',
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            // Soft delete
            $user->delete();
        });
    }

    /**
     * Invalidate all user sessions and tokens.
     */
    public function invalidateAllSessions(User $user): void
    {
        // Blacklist all tokens
        $this->tokenBlacklistService->blacklistAllUserTokens($user->id, 'user_deactivated');

        // Clear active sessions
        DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Invalidate sessions on role change.
     */
    public function onRoleChange(User $user): void
    {
        // Update timestamp so we can detect stale tokens
        $user->update(['roles_updated_at' => now()]);

        // Blacklist all current tokens - user must re-login
        $this->tokenBlacklistService->blacklistAllUserTokens($user->id, 'role_change');
    }

    /**
     * Track a new session.
     */
    public function trackSession(User $user, string $tokenId, string $ipAddress, ?string $userAgent = null): void
    {
        DB::table('user_sessions')->insert([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'device_type' => $this->detectDeviceType($userAgent),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(config('jwt.ttl', 60)),
            'created_at' => now(),
        ]);
    }

    /**
     * Update session activity.
     */
    public function updateSessionActivity(string $tokenId): void
    {
        DB::table('user_sessions')
            ->where('token_id', $tokenId)
            ->update(['last_activity_at' => now()]);
    }

    /**
     * Get active sessions for a user.
     */
    public function getActiveSessions(User $user): array
    {
        return DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_activity_at')
            ->get()
            ->toArray();
    }

    /**
     * Terminate a specific session.
     */
    public function terminateSession(User $user, string $tokenId): bool
    {
        $deleted = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->where('token_id', $tokenId)
            ->delete();

        if ($deleted) {
            $this->tokenBlacklistService->blacklistToken(
                $tokenId,
                $user->id,
                'session_terminated',
                now()->addMinutes(config('jwt.ttl', 60))->timestamp
            );
        }

        return $deleted > 0;
    }

    /**
     * Complete user onboarding.
     */
    public function completeOnboarding(User $user): void
    {
        if ($user->onboarding_completed_at) {
            return; // Already completed
        }

        $user->update(['onboarding_completed_at' => now()]);
    }

    /**
     * Check if user has completed onboarding.
     */
    public function hasCompletedOnboarding(User $user): bool
    {
        return $user->onboarding_completed_at !== null;
    }

    /**
     * Validate that we're not deleting the last admin.
     */
    protected function validateNotLastAdmin(User $user): void
    {
        // Check if user is an admin
        $isAdmin = $user->roles()
            ->where('slug', 'admin')
            ->exists();

        if (!$isAdmin) {
            return;
        }

        // Count other active admins in the organization
        $otherAdmins = User::where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('roles', fn($q) => $q->where('slug', 'admin'))
            ->count();

        if ($otherAdmins === 0) {
            throw new InvalidArgumentException(
                'Cannot deactivate or delete the last administrator. Please assign another admin first.'
            );
        }
    }

    /**
     * Detect device type from user agent.
     */
    protected function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Cleanup expired sessions (run via scheduler).
     */
    public function cleanupExpiredSessions(): int
    {
        return DB::table('user_sessions')
            ->where('expires_at', '<', now())
            ->delete();
    }
}

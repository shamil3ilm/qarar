<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ActivityLog;
use App\Models\Core\EntityView;
use App\Models\Core\LoginHistory;
use App\Models\UserSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityLogService
{
    private const CRITICAL_ACTIONS = [
        \App\Models\Core\ActivityLog::ACTION_CREATED,
        \App\Models\Core\ActivityLog::ACTION_UPDATED,
        \App\Models\Core\ActivityLog::ACTION_DELETED,
        \App\Models\Core\ActivityLog::ACTION_RESTORED,
        \App\Models\Core\ActivityLog::ACTION_APPROVED,
        \App\Models\Core\ActivityLog::ACTION_REJECTED,
        \App\Models\Core\ActivityLog::ACTION_SUBMITTED,
        \App\Models\Core\ActivityLog::ACTION_EXPORTED,
        \App\Models\Core\ActivityLog::ACTION_EMAILED,
        \App\Models\Core\ActivityLog::ACTION_PRINTED,
        \App\Models\Core\ActivityLog::ACTION_ARCHIVED,
        \App\Models\Core\ActivityLog::ACTION_IMPERSONATION_STARTED,
        \App\Models\Core\ActivityLog::ACTION_IMPERSONATION_ENDED,
    ];

    /**
     * Log an activity.
     */
    public function log(array $data): ActivityLog
    {
        $user = auth()->user();

        // Auto-stamp impersonation context when set by TrackImpersonation middleware
        $impersonatedById = $data['impersonated_by_id']
            ?? request()->attributes->get('impersonated_by_id');
        $impersonationSessionId = $data['impersonation_session_id']
            ?? request()->attributes->get('impersonation_session_id');

        $shouldStamp = $impersonatedById !== null
            && in_array($data['action'], self::CRITICAL_ACTIONS, true);

        return ActivityLog::create([
            'organization_id' => $data['organization_id'] ?? $user?->organization_id,
            'user_id' => $data['user_id'] ?? $user?->id,
            'branch_id' => $data['branch_id'] ?? $user?->current_branch_id,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'entity_name' => $data['entity_name'] ?? null,
            'description' => $data['description'],
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'changed_fields' => $data['changed_fields'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'request_method' => $data['request_method'] ?? request()->method(),
            'request_url' => $data['request_url'] ?? request()->fullUrl(),
            'session_id' => $data['session_id'] ?? session()->getId(),
            'module' => $data['module'] ?? null,
            'severity' => $data['severity'] ?? ActivityLog::SEVERITY_INFO,
            'is_system' => $data['is_system'] ?? false,
            'impersonated_by_id'       => $shouldStamp ? $impersonatedById : null,
            'impersonation_session_id' => $shouldStamp ? $impersonationSessionId : null,
        ]);
    }

    /**
     * Log activity for a model (convenience method).
     */
    public function logForModel(Model $model, string $action, string $description, ?array $extra = null): ActivityLog
    {
        return $this->log([
            'organization_id' => $model->organization_id ?? null,
            'action' => $action,
            'entity_type' => class_basename($model),
            'entity_id' => (string) $model->getKey(),
            'entity_name' => $model->name ?? $model->title ?? null,
            'description' => $description,
            'metadata' => $extra,
        ]);
    }

    /**
     * Get activity logs for a specific entity.
     */
    public function getForEntity(
        string $entityType,
        string $entityId,
        int $perPage = 20,
        ?string $action = null
    ): LengthAwarePaginator {
        $query = ActivityLog::forEntity($entityType, $entityId)
            ->with('user')
            ->orderByDesc('created_at');

        if ($action) {
            $query->byAction($action);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get activity logs for a specific user.
     */
    public function getForUser(
        int $userId,
        int $perPage = 20,
        ?string $action = null,
        ?string $module = null
    ): LengthAwarePaginator {
        $query = ActivityLog::byUser($userId)
            ->orderByDesc('created_at');

        if ($action) {
            $query->byAction($action);
        }

        if ($module) {
            $query->byModule($module);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all activity logs with filters.
     */
    public function getAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->with('user')
            ->orderByDesc('created_at');

        if (!empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['module'])) {
            $query->byModule($filters['module']);
        }

        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }

        if (isset($filters['is_system'])) {
            $filters['is_system'] ? $query->systemOnly() : $query->userOnly();
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('entity_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get active user sessions.
     */
    public function getUserSessions(int $userId): Collection
    {
        return UserSession::forUser($userId)
            ->active()
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * Get all active sessions (for admin view).
     */
    public function getAllActiveSessions(int $perPage = 20): LengthAwarePaginator
    {
        return UserSession::active()
            ->with('user')
            ->orderByDesc('last_activity_at')
            ->paginate($perPage);
    }

    /**
     * Terminate a specific session.
     */
    public function terminateSession(UserSession $session, string $reason = UserSession::LOGOUT_FORCED): void
    {
        $session->terminate($reason);
    }

    /**
     * Get login history for a user.
     */
    public function getLoginHistory(
        ?int $userId = null,
        int $perPage = 20,
        ?string $status = null
    ): LengthAwarePaginator {
        $query = LoginHistory::query()->orderByDesc('attempted_at');

        if ($userId) {
            $query->forUser($userId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Record an entity view (for "recently viewed" feature).
     */
    public function recordEntityView(
        string $entityType,
        string $entityId,
        ?string $entityName = null,
        int $userId = 0,
        ?int $organizationId = null
    ): EntityView {
        if ($userId === 0) {
            $userId = (int) auth()->id();
        }
        $organizationId = $organizationId ?? auth()->user()?->organization_id;

        return EntityView::updateOrCreate(
            [
                'user_id' => $userId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'organization_id' => $organizationId,
                'entity_name' => $entityName,
                'viewed_at' => now(),
            ]
        );
    }

    /**
     * Get recently viewed entities for a user.
     */
    public function getRecentlyViewed(int $userId, int $limit = 20, ?string $entityType = null): Collection
    {
        $query = EntityView::forUser($userId)
            ->orderByDesc('viewed_at')
            ->limit($limit);

        if ($entityType) {
            $query->forEntityType($entityType);
        }

        return $query->get();
    }

    /**
     * Get popular entities (most viewed across organization).
     */
    public function getPopularEntities(
        int $organizationId,
        int $limit = 10,
        ?string $entityType = null,
        int $days = 30
    ): Collection {
        $query = EntityView::where('organization_id', $organizationId)
            ->where('viewed_at', '>=', now()->subDays($days))
            ->select('entity_type', 'entity_id', 'entity_name', DB::raw('COUNT(*) as view_count'))
            ->groupBy('entity_type', 'entity_id', 'entity_name')
            ->orderByDesc('view_count')
            ->limit($limit);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->get();
    }

    /**
     * Get activity statistics for the organization.
     */
    public function getStatistics(int $organizationId, int $days = 30): array
    {
        $query = ActivityLog::where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->subDays($days));

        return [
            'total_activities' => (clone $query)->count(),
            'by_action' => (clone $query)
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'by_module' => (clone $query)
                ->whereNotNull('module')
                ->select('module', DB::raw('COUNT(*) as count'))
                ->groupBy('module')
                ->pluck('count', 'module')
                ->toArray(),
            'by_severity' => (clone $query)
                ->select('severity', DB::raw('COUNT(*) as count'))
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
            'active_users' => (clone $query)
                ->distinct('user_id')
                ->count('user_id'),
            'by_day' => (clone $query)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray(),
        ];
    }

    /**
     * Clean up old activity logs.
     */
    public function cleanup(int $daysToKeep = 365): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }
}

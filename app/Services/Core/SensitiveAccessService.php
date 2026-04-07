<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\SensitiveAccessLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensitiveAccessService
{
    /**
     * Log a sensitive model access event.
     *
     * Silently fails if the request context is unavailable (e.g. console).
     */
    public function logAccess(
        string $modelType,
        int $modelId,
        ?string $fields = null,
        string $action = 'read',
    ): void {
        try {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            // Deduplicate: skip if the same user already logged this model within 60 seconds.
            $dedupeKey = "sal_dedup:{$user->id}:{$modelType}:{$modelId}:{$action}";
            if (cache()->has($dedupeKey)) {
                return;
            }
            cache()->put($dedupeKey, true, 60);

            /** @var Request|null $request */
            $request = app()->bound('request') ? app('request') : null;

            SensitiveAccessLog::create([
                'organization_id'  => $user->organization_id,
                'user_id'          => $user->id,
                'model_type'       => $modelType,
                'model_id'         => $modelId,
                'action'           => $action,
                'sensitive_fields' => $fields,
                'ip_address'       => $request?->ip(),
                'user_agent'       => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SensitiveAccessService::logAccess failed', [
                'model_type' => $modelType,
                'model_id'   => $modelId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Paginated access log with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAccessReport(array $filters): LengthAwarePaginator
    {
        $orgId = auth()->user()?->organization_id;

        $query = SensitiveAccessLog::with('user')
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when(!empty($filters['model_type']), fn ($q) => $q->where('model_type', $filters['model_type']))
            ->when(!empty($filters['user_id']),    fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(!empty($filters['action']),     fn ($q) => $q->where('action', $filters['action']))
            ->when(
                !empty($filters['from']),
                fn ($q) => $q->whereDate('created_at', '>=', $filters['from'])
            )
            ->when(
                !empty($filters['to']),
                fn ($q) => $q->whereDate('created_at', '<=', $filters['to'])
            )
            ->orderByDesc('created_at');

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * All access events for a specific document.
     */
    public function getAccessByDocument(string $modelType, int $modelId): Collection
    {
        return SensitiveAccessLog::with('user')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Detect suspicious activity:
     *   – Users who accessed >50 sensitive records in the last 24 hours.
     *   – Users who performed bulk export/print of sensitive data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSuspiciousActivity(int $orgId): array
    {
        $since = now()->subDay();

        // High-volume readers
        $highVolume = SensitiveAccessLog::where('organization_id', $orgId)
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as access_count')
            ->groupBy('user_id')
            ->having('access_count', '>', 50)
            ->with('user')
            ->get()
            ->map(fn ($row) => [
                'type'         => 'high_volume_access',
                'user_id'      => $row->user_id,
                'user_name'    => $row->user?->name,
                'access_count' => $row->access_count,
                'since'        => $since->toDateTimeString(),
            ])
            ->toArray();

        // Bulk exporters / printers
        $bulkActions = SensitiveAccessLog::where('organization_id', $orgId)
            ->where('created_at', '>=', $since)
            ->whereIn('action', ['export', 'print'])
            ->selectRaw('user_id, action, COUNT(*) as action_count')
            ->groupBy('user_id', 'action')
            ->having('action_count', '>', 10)
            ->with('user')
            ->get()
            ->map(fn ($row) => [
                'type'         => 'bulk_' . $row->action,
                'user_id'      => $row->user_id,
                'user_name'    => $row->user?->name,
                'action_count' => $row->action_count,
                'since'        => $since->toDateTimeString(),
            ])
            ->toArray();

        return array_merge($highVolume, $bulkActions);
    }
}

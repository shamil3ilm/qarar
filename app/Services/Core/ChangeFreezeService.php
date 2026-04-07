<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ChangeFreezeperiod;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ChangeFreezeService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Return all currently active freeze periods for the given organisation.
     * Results are cached for CACHE_TTL_SECONDS seconds to reduce DB load on
     * every inbound request.
     */
    public function getActiveFreezes(int $organizationId): Collection
    {
        $cacheKey = "change_freeze:active:{$organizationId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($organizationId) {
            return ChangeFreezeperiod::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->get();
        });
    }

    /**
     * Return true when at least one active freeze covers the given module
     * and the user cannot bypass it.
     */
    public function isFrozen(int $organizationId, string $module, User $user): bool
    {
        $freezes = $this->getActiveFreezes($organizationId);

        foreach ($freezes as $freeze) {
            if ($freeze->affectsModule($module) && ! $freeze->canBypass($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new change freeze period.
     */
    public function createFreeze(array $data, int $createdBy): ChangeFreezeperiod
    {
        $freeze = ChangeFreezeperiod::create(array_merge($data, ['created_by' => $createdBy]));

        $this->bustCache($freeze->organization_id);

        return $freeze;
    }

    /**
     * Deactivate a freeze by setting is_active = false and ends_at = now().
     */
    public function endFreeze(int $id): void
    {
        $freeze = ChangeFreezeperiod::findOrFail($id);
        $freeze->update([
            'is_active' => false,
            'ends_at'   => now(),
        ]);

        $this->bustCache($freeze->organization_id);
    }

    /**
     * Paginated list of all freeze periods (including inactive) for an org.
     */
    public function listFreezes(int $organizationId): LengthAwarePaginator
    {
        return ChangeFreezeperiod::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function bustCache(int $organizationId): void
    {
        Cache::forget("change_freeze:active:{$organizationId}");
    }
}

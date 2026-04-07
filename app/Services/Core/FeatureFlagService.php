<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\FeatureFlag;
use App\Models\Core\FeatureFlagRolloutLog;
use App\Models\Core\FeatureFlagTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_TTL = 30; // seconds

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Determine whether a feature flag is active for a specific user.
     *
     * Resolution order:
     *   1. If no targets exist for the flag → fall back to org-level feature_flags value.
     *   2. Direct user target match → return its enabled value.
     *   3. Branch target match → return its enabled value.
     *   4. Role target match (any role in $userRoles) → return enabled.
     *   5. Percentage target → deterministic bucket check.
     *   6. Default → false (not yet rolled out to this user).
     */
    public function isEnabledForUser(
        string $flagKey,
        int $organizationId,
        int $userId,
        array $userRoles = [],
        ?int $branchId = null,
    ): bool {
        $cacheKey = "fft:{$organizationId}:{$flagKey}:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $flagKey,
            $organizationId,
            $userId,
            $userRoles,
            $branchId,
        ) {
            $targets = FeatureFlagTarget::where('organization_id', $organizationId)
                ->where('flag_key', $flagKey)
                ->get();

            // No targeting configured → use org-level flag
            if ($targets->isEmpty()) {
                return FeatureFlag::isEnabled($organizationId, $flagKey);
            }

            // 1. Direct user target
            $userTarget = $targets->first(
                fn(FeatureFlagTarget $t) =>
                    $t->target_type === FeatureFlagTarget::TYPE_USER
                    && (int) $t->target_id === $userId
            );

            if ($userTarget !== null) {
                return $userTarget->enabled;
            }

            // 2. Branch target
            if ($branchId !== null) {
                $branchTarget = $targets->first(
                    fn(FeatureFlagTarget $t) =>
                        $t->target_type === FeatureFlagTarget::TYPE_BRANCH
                        && (int) $t->target_id === $branchId
                );

                if ($branchTarget !== null) {
                    return $branchTarget->enabled;
                }
            }

            // 3. Role targets
            $roleTargets = $targets->filter(
                fn(FeatureFlagTarget $t) => $t->target_type === FeatureFlagTarget::TYPE_ROLE
            );

            foreach ($roleTargets as $roleTarget) {
                if (in_array((int) $roleTarget->target_id, $userRoles, true)) {
                    return $roleTarget->enabled;
                }
            }

            // 4. Percentage target (deterministic bucket)
            $percentageTarget = $targets->first(
                fn(FeatureFlagTarget $t) => $t->target_type === FeatureFlagTarget::TYPE_PERCENTAGE
            );

            if ($percentageTarget !== null && $percentageTarget->enabled) {
                $bucket = abs(crc32($flagKey . $userId)) % 100;
                return $bucket < (int) $percentageTarget->percentage;
            }

            // Default: not in rollout
            return false;
        });
    }

    /**
     * Add a targeting rule to a feature flag.
     */
    public function addTarget(
        int $organizationId,
        string $flagKey,
        string $targetType,
        ?int $targetId,
        ?int $percentage,
        int $createdBy,
    ): FeatureFlagTarget {
        $target = FeatureFlagTarget::create([
            'organization_id' => $organizationId,
            'flag_key'        => $flagKey,
            'target_type'     => $targetType,
            'target_id'       => $targetId,
            'percentage'      => $percentage,
            'enabled'         => true,
            'created_by'      => $createdBy,
        ]);

        $this->logAction($organizationId, $flagKey, FeatureFlagRolloutLog::ACTION_TARGET_ADDED, $createdBy, [
            'target_id'   => $target->id,
            'target_type' => $targetType,
            'ref_id'      => $targetId,
            'percentage'  => $percentage,
        ]);

        $this->burstCache($organizationId, $flagKey);

        return $target;
    }

    /**
     * Remove a targeting rule by its primary key.
     */
    public function removeTarget(int $targetId): void
    {
        $target = FeatureFlagTarget::findOrFail($targetId);

        $organizationId = (int) $target->organization_id;
        $flagKey        = $target->flag_key;

        // Resolve the actor from the request context if available
        $actorId = auth()->id() ?? 0;

        $target->delete();

        $this->logAction($organizationId, $flagKey, FeatureFlagRolloutLog::ACTION_TARGET_REMOVED, $actorId, [
            'target_id'   => $targetId,
            'target_type' => $target->target_type,
            'ref_id'      => $target->target_id,
        ]);

        $this->burstCache($organizationId, $flagKey);
    }

    /**
     * Set or update the percentage rollout target for a flag.
     */
    public function setRolloutPercentage(
        int $organizationId,
        string $flagKey,
        int $percentage,
        int $actorId,
    ): FeatureFlagTarget {
        $target = FeatureFlagTarget::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'flag_key'        => $flagKey,
                'target_type'     => FeatureFlagTarget::TYPE_PERCENTAGE,
                'target_id'       => null,
            ],
            [
                'percentage' => $percentage,
                'enabled'    => true,
                'created_by' => $actorId,
            ]
        );

        $this->logAction(
            $organizationId,
            $flagKey,
            FeatureFlagRolloutLog::ACTION_ROLLOUT_PERCENTAGE_SET,
            $actorId,
            ['percentage' => $percentage]
        );

        $this->burstCache($organizationId, $flagKey);

        return $target;
    }

    /**
     * Return all targeting rules for a flag in an organization.
     */
    public function listTargets(int $organizationId, string $flagKey): Collection
    {
        return FeatureFlagTarget::where('organization_id', $organizationId)
            ->where('flag_key', $flagKey)
            ->orderBy('target_type')
            ->get();
    }

    /**
     * Append an immutable rollout log entry.
     */
    public function logAction(
        int $organizationId,
        string $flagKey,
        string $action,
        int $actorId,
        array $detail = [],
    ): void {
        FeatureFlagRolloutLog::create([
            'organization_id' => $organizationId,
            'flag_key'        => $flagKey,
            'action'          => $action,
            'actor_id'        => $actorId ?: null,
            'detail'          => $detail ?: null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Clear all per-user caches for a given org+flag combination.
     * Because we cannot enumerate every user, we use a cache tag when
     * tags are supported; otherwise we accept that individual caches
     * will expire within CACHE_TTL seconds.
     */
    private function burstCache(int $organizationId, string $flagKey): void
    {
        // Flush the tag group if the cache driver supports tags
        try {
            Cache::tags(["fft:{$organizationId}:{$flagKey}"])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags — caches expire naturally
        }
    }
}

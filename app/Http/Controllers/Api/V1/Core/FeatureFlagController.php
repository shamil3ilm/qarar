<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\FeatureFlag;
use App\Models\Core\FeatureFlagTarget;
use App\Services\Core\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    // -------------------------------------------------------------------------
    // List all feature flags with their targets for the organization
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/feature-flags
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $allFlags = FeatureFlag::getAllForOrganization($organizationId);

        $flagsWithTargets = collect($allFlags)->map(function (array $flag, string $key) use ($organizationId) {
            $flag['flag_key'] = $key;
            $flag['targets']  = $this->featureFlagService->listTargets($organizationId, $key);
            return $flag;
        })->values();

        return $this->success($flagsWithTargets, 'Feature flags retrieved successfully');
    }

    // -------------------------------------------------------------------------
    // Show a single flag with its targets
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/feature-flags/{flagKey}
     */
    public function show(Request $request, string $flagKey): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $isEnabled = FeatureFlag::isEnabled($organizationId, $flagKey);
        $config    = FeatureFlag::getConfig($organizationId, $flagKey);
        $targets   = $this->featureFlagService->listTargets($organizationId, $flagKey);

        return $this->success([
            'flag_key'    => $flagKey,
            'enabled'     => $isEnabled,
            'config'      => $config,
            'description' => FeatureFlag::FEATURES[$flagKey] ?? null,
            'targets'     => $targets,
        ], 'Feature flag retrieved successfully');
    }

    // -------------------------------------------------------------------------
    // Add a targeting rule
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/feature-flags/{flagKey}/targets
     */
    public function addTarget(Request $request, string $flagKey): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:user,branch,role,percentage',
            'target_id'   => 'nullable|integer|min:1|required_unless:target_type,percentage',
            'percentage'  => 'nullable|integer|min:0|max:100|required_if:target_type,percentage',
            'notes'       => 'nullable|string|max:500',
        ]);

        $organizationId = auth()->user()->organization_id;
        $actorId        = auth()->id();

        $target = $this->featureFlagService->addTarget(
            organizationId: $organizationId,
            flagKey:        $flagKey,
            targetType:     $validated['target_type'],
            targetId:       isset($validated['target_id']) ? (int) $validated['target_id'] : null,
            percentage:     isset($validated['percentage']) ? (int) $validated['percentage'] : null,
            createdBy:      $actorId,
        );

        if (!empty($validated['notes'])) {
            $target->update(['notes' => $validated['notes']]);
        }

        return $this->created($target, 'Target added successfully');
    }

    // -------------------------------------------------------------------------
    // Remove a targeting rule
    // -------------------------------------------------------------------------

    /**
     * DELETE /api/v1/feature-flags/{flagKey}/targets/{targetId}
     */
    public function removeTarget(Request $request, string $flagKey, int $targetId): JsonResponse
    {
        // Verify the target belongs to this flag and organization
        $organizationId = auth()->user()->organization_id;

        $target = FeatureFlagTarget::where('id', $targetId)
            ->where('organization_id', $organizationId)
            ->where('flag_key', $flagKey)
            ->firstOrFail();

        $this->featureFlagService->removeTarget($target->id);

        return $this->success(null, 'Target removed successfully');
    }

    // -------------------------------------------------------------------------
    // Set rollout percentage
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/feature-flags/{flagKey}/rollout-percentage
     */
    public function setPercentage(Request $request, string $flagKey): JsonResponse
    {
        $validated = $request->validate([
            'percentage' => 'required|integer|min:0|max:100',
        ]);

        $organizationId = auth()->user()->organization_id;
        $actorId        = auth()->id();

        $target = $this->featureFlagService->setRolloutPercentage(
            organizationId: $organizationId,
            flagKey:        $flagKey,
            percentage:     (int) $validated['percentage'],
            actorId:        $actorId,
        );

        return $this->success($target, 'Rollout percentage updated successfully');
    }

    // -------------------------------------------------------------------------
    // Check flag for a specific user
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/feature-flags/{flagKey}/check?user_id=X
     */
    public function checkForUser(Request $request, string $flagKey): JsonResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|integer|min:1',
            'branch_id'  => 'nullable|integer|min:1',
            'role_ids'   => 'nullable|array',
            'role_ids.*' => 'integer|min:1',
        ]);

        $organizationId = auth()->user()->organization_id;
        $userId         = (int) $validated['user_id'];
        $branchId       = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $roleIds        = $validated['role_ids'] ?? [];

        $targets = $this->featureFlagService->listTargets($organizationId, $flagKey);

        $enabled = $this->featureFlagService->isEnabledForUser(
            flagKey:        $flagKey,
            organizationId: $organizationId,
            userId:         $userId,
            userRoles:      $roleIds,
            branchId:       $branchId,
        );

        $reason = $this->resolveReason(
            flagKey:        $flagKey,
            organizationId: $organizationId,
            userId:         $userId,
            roleIds:        $roleIds,
            branchId:       $branchId,
            enabled:        $enabled,
            hasTargets:     $targets->isNotEmpty(),
        );

        return $this->success([
            'flag_key' => $flagKey,
            'user_id'  => $userId,
            'enabled'  => $enabled,
            'reason'   => $reason,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function resolveReason(
        string $flagKey,
        int $organizationId,
        int $userId,
        array $roleIds,
        ?int $branchId,
        bool $enabled,
        bool $hasTargets,
    ): string {
        if (!$hasTargets) {
            return $enabled ? 'org_flag_enabled' : 'org_flag_disabled';
        }

        $targets = $this->featureFlagService->listTargets($organizationId, $flagKey);

        $userTarget = $targets->first(
            fn(FeatureFlagTarget $t) =>
                $t->target_type === FeatureFlagTarget::TYPE_USER
                && (int) $t->target_id === $userId
        );
        if ($userTarget !== null) {
            return $userTarget->enabled ? 'user_target_enabled' : 'user_target_disabled';
        }

        if ($branchId !== null) {
            $branchTarget = $targets->first(
                fn(FeatureFlagTarget $t) =>
                    $t->target_type === FeatureFlagTarget::TYPE_BRANCH
                    && (int) $t->target_id === $branchId
            );
            if ($branchTarget !== null) {
                return $branchTarget->enabled ? 'branch_target_enabled' : 'branch_target_disabled';
            }
        }

        $roleTargets = $targets->filter(
            fn(FeatureFlagTarget $t) => $t->target_type === FeatureFlagTarget::TYPE_ROLE
        );
        foreach ($roleTargets as $roleTarget) {
            if (in_array((int) $roleTarget->target_id, $roleIds, true)) {
                return 'role_target_matched';
            }
        }

        $percentageTarget = $targets->first(
            fn(FeatureFlagTarget $t) => $t->target_type === FeatureFlagTarget::TYPE_PERCENTAGE
        );
        if ($percentageTarget !== null) {
            return $enabled ? 'percentage_rollout_in_bucket' : 'percentage_rollout_not_in_bucket';
        }

        return 'no_matching_target';
    }
}

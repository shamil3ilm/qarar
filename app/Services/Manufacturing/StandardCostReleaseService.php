<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CostingVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CO-03: Release a standard cost estimate to production (SAP CK11N / CK24 equivalent).
 *
 * "Releasing" a costing version means:
 *   1. Validate the version is in draft or frozen status.
 *   2. Archive any currently active version for the same organisation.
 *   3. Activate the target version so it becomes the live standard cost.
 */
class StandardCostReleaseService
{
    /**
     * Release a costing version to production (make it the active standard cost).
     *
     * @throws InvalidArgumentException When the version cannot be released.
     */
    public function release(CostingVersion $version, int $releasedByUserId): CostingVersion
    {
        $this->assertReleasable($version);

        return DB::transaction(function () use ($version, $releasedByUserId): CostingVersion {
            $this->archiveCurrentActive($version->organization_id);

            $version->update([
                'status'       => CostingVersion::STATUS_ACTIVE,
                'released_by'  => $releasedByUserId,
                'released_at'  => now(),
            ]);

            return $version->fresh();
        });
    }

    /**
     * Freeze a costing version (mark prices as final, no further edits).
     * A frozen version can still be released to production.
     *
     * @throws InvalidArgumentException When the version is not in draft status.
     */
    public function freeze(CostingVersion $version): CostingVersion
    {
        if ($version->status !== CostingVersion::STATUS_DRAFT) {
            throw new InvalidArgumentException(
                "Only draft costing versions can be frozen (current status: {$version->status})."
            );
        }

        $version->update(['status' => CostingVersion::STATUS_FROZEN]);

        return $version->fresh();
    }

    /**
     * Roll back the active version to a prior frozen version.
     * The currently active version is archived and the target frozen version is activated.
     *
     * @throws InvalidArgumentException When target is not frozen.
     */
    public function rollback(CostingVersion $target, int $userId): CostingVersion
    {
        if ($target->status !== CostingVersion::STATUS_FROZEN) {
            throw new InvalidArgumentException(
                "Rollback target must be a frozen version (current status: {$target->status})."
            );
        }

        return DB::transaction(function () use ($target, $userId): CostingVersion {
            $this->archiveCurrentActive($target->organization_id);

            $target->update([
                'status'      => CostingVersion::STATUS_ACTIVE,
                'released_by' => $userId,
                'released_at' => now(),
            ]);

            return $target->fresh();
        });
    }

    /**
     * Return a summary of the cost estimate for the version.
     *
     * @return array{
     *   version_id: int,
     *   status: string,
     *   product_count: int,
     *   avg_material_cost: float,
     *   avg_labor_cost: float,
     *   avg_overhead_cost: float,
     *   avg_total_cost: float,
     * }
     */
    public function summary(CostingVersion $version): array
    {
        $agg = $version->standardCosts()
            ->selectRaw('
                COUNT(*) as product_count,
                AVG(material_cost) as avg_material,
                AVG(labor_cost) as avg_labor,
                AVG(overhead_cost) as avg_overhead,
                AVG(total_standard_cost) as avg_total
            ')
            ->first();

        return [
            'version_id'        => $version->id,
            'status'            => $version->status,
            'product_count'     => (int) ($agg->product_count ?? 0),
            'avg_material_cost' => round((float) ($agg->avg_material ?? 0), 4),
            'avg_labor_cost'    => round((float) ($agg->avg_labor ?? 0), 4),
            'avg_overhead_cost' => round((float) ($agg->avg_overhead ?? 0), 4),
            'avg_total_cost'    => round((float) ($agg->avg_total ?? 0), 4),
        ];
    }

    private function assertReleasable(CostingVersion $version): void
    {
        if (!in_array($version->status, [CostingVersion::STATUS_DRAFT, CostingVersion::STATUS_FROZEN], true)) {
            throw new InvalidArgumentException(
                "Costing version cannot be released from status '{$version->status}'. "
                . "Only draft or frozen versions can be released."
            );
        }
    }

    private function archiveCurrentActive(int $organizationId): void
    {
        CostingVersion::where('organization_id', $organizationId)
            ->where('status', CostingVersion::STATUS_ACTIVE)
            ->update(['status' => CostingVersion::STATUS_ARCHIVED]);
    }
}

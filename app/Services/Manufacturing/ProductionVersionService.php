<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductionVersionService
{
    /**
     * Retrieve the default production version for a product.
     */
    public function getDefaultVersion(int $productId): ?ProductionVersion
    {
        return ProductionVersion::active()
            ->defaultForProduct($productId)
            ->first();
    }

    /**
     * Find the first active version whose lot size range covers the given quantity.
     * Falls back to the default version when no range-specific match is found.
     */
    public function getVersionForLotSize(int $productId, float $quantity): ?ProductionVersion
    {
        $versions = ProductionVersion::active()
            ->forProduct($productId)
            ->orderByDesc('is_default')
            ->get();

        foreach ($versions as $version) {
            if ($version->isValidForLotSize($quantity)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Create a new production version.
     */
    public function create(array $data): ProductionVersion
    {
        return DB::transaction(function () use ($data): ProductionVersion {
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->clearDefaultFlag((int) $data['product_id']);
            }

            return ProductionVersion::create($data);
        });
    }

    /**
     * Update an existing production version.
     */
    public function update(ProductionVersion $version, array $data): ProductionVersion
    {
        return DB::transaction(function () use ($version, $data): ProductionVersion {
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->clearDefaultFlag((int) ($data['product_id'] ?? $version->product_id), $version->id);
            }

            $version->update($data);

            return $version->fresh();
        });
    }

    /**
     * Set a version as the default for its product, unsetting all others.
     */
    public function setDefault(ProductionVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $this->clearDefaultFlag($version->product_id, $version->id);

            $version->update(['is_default' => true]);
        });
    }

    /**
     * Retrieve all versions for a product, ordered so the default comes first.
     */
    public function getVersionsForProduct(int $productId): Collection
    {
        return ProductionVersion::forProduct($productId)
            ->with(['bom', 'routing'])
            ->orderByDesc('is_default')
            ->orderBy('version_code')
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Clear the is_default flag for all versions of a product except the given one.
     */
    private function clearDefaultFlag(int $productId, ?int $exceptId = null): void
    {
        ProductionVersion::forProduct($productId)
            ->where('is_default', true)
            ->when($exceptId !== null, fn($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}

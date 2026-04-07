<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StorageType;
use App\Models\Inventory\StorageTypeDeterminationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StorageTypeDeterminationService
{
    /**
     * Paginate storage types with optional filters.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = StorageType::with('warehouse:id,name')
            ->orderBy('storage_type_code');

        if (!empty($filters['warehouse_id'])) {
            $query->forWarehouse((int) $filters['warehouse_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['storage_class'])) {
            $query->where('storage_class', $filters['storage_class']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Create a new storage type.
     */
    public function createType(array $data): StorageType
    {
        return StorageType::create($data);
    }

    /**
     * Update an existing storage type.
     */
    public function updateType(StorageType $storageType, array $data): StorageType
    {
        $storageType->update($data);

        return $storageType->refresh();
    }

    /**
     * Add a determination rule to a storage type.
     */
    public function addRule(StorageType $storageType, array $data): StorageTypeDeterminationRule
    {
        return StorageTypeDeterminationRule::create([
            ...$data,
            'organization_id' => $storageType->organization_id,
            'storage_type_id' => $storageType->id,
            'warehouse_id'    => $data['warehouse_id'] ?? $storageType->warehouse_id,
        ]);
    }

    /**
     * Update an existing determination rule.
     */
    public function updateRule(StorageTypeDeterminationRule $rule, array $data): StorageTypeDeterminationRule
    {
        $rule->update($data);

        return $rule->refresh();
    }

    /**
     * Determine the best matching storage type for a given movement.
     *
     * Rules are filtered by:
     *  1. Warehouse and movement type (required)
     *  2. Product storage class match (if rule specifies one)
     *  3. Max weight constraint (if rule specifies one)
     *
     * Rules are ordered by ascending priority (lower number = higher priority).
     * The first matching storage type that still has available capacity is returned.
     */
    public function determineStorageType(
        int $warehouseId,
        string $movementType,
        ?string $productStorageClass = null,
        ?float $weight = null
    ): ?StorageType {
        $rules = StorageTypeDeterminationRule::with('storageType')
            ->active()
            ->forMovement($movementType)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            // Filter by product storage class if the rule restricts it
            if ($rule->product_storage_class !== null
                && $productStorageClass !== null
                && $rule->product_storage_class !== $productStorageClass) {
                continue;
            }

            // Filter by weight constraint if the rule restricts it
            if ($rule->max_weight_kg !== null
                && $weight !== null
                && $weight > (float) $rule->max_weight_kg) {
                continue;
            }

            $storageType = $rule->storageType;

            if ($storageType === null || !$storageType->is_active) {
                continue;
            }

            if ($storageType->hasCapacity()) {
                return $storageType;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\HazmatClassification;
use App\Models\Inventory\HazmatStorageCompatibilityRule;
use App\Models\Inventory\HazmatTransportRegulation;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductHazmatClassification;
use App\Models\Inventory\SafetyDataSheet;
use App\Models\Inventory\SafetyDataSheetSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HazmatService
{
    /**
     * Assign hazmat classifications to a product.
     * Replaces any existing classifications for the product.
     */
    public function classifyProduct(int $productId, array $classificationData): void
    {
        DB::transaction(function () use ($productId, $classificationData): void {
            // Remove existing classifications for this product.
            ProductHazmatClassification::where('product_id', $productId)->delete();

            foreach ($classificationData as $item) {
                ProductHazmatClassification::create([
                    'product_id'               => $productId,
                    'hazmat_classification_id' => $item['hazmat_classification_id'],
                    'storage_class_id'         => $item['storage_class_id'] ?? null,
                    'is_primary'               => $item['is_primary'] ?? false,
                ]);
            }
        });
    }

    /**
     * Return the current (latest revision) Safety Data Sheet for a product in a given language.
     */
    public function getCurrentSds(int $productId, string $language = 'en'): ?SafetyDataSheet
    {
        return SafetyDataSheet::with('sections')
            ->forProduct($productId)
            ->current()
            ->forLanguage($language)
            ->latest('revision_date')
            ->first();
    }

    /**
     * Create a new Safety Data Sheet and optionally mark it as the current version.
     */
    public function createSds(array $data): SafetyDataSheet
    {
        return DB::transaction(function () use ($data): SafetyDataSheet {
            $sections = $data['sections'] ?? [];
            unset($data['sections']);

            $markCurrent = (bool) ($data['is_current'] ?? true);
            $data['is_current'] = false; // will be set below after creation

            $sds = SafetyDataSheet::create($data);

            foreach ($sections as $sectionData) {
                SafetyDataSheetSection::create(array_merge($sectionData, [
                    'safety_data_sheet_id' => $sds->id,
                ]));
            }

            if ($markCurrent) {
                $sds->markAsCurrentVersion();
            }

            return $sds->load('sections');
        });
    }

    /**
     * Check whether two storage classes are compatible for co-storage.
     * Returns true when compatible, false when incompatible, and true (permissive) when no rule exists.
     */
    public function checkStorageCompatibility(int $storageClassAId, int $storageClassBId): bool
    {
        $organizationId = auth()->user()->organization_id;

        $rule = HazmatStorageCompatibilityRule::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($storageClassAId, $storageClassBId): void {
                $query->where(function ($q) use ($storageClassAId, $storageClassBId): void {
                    $q->where('storage_class_a_id', $storageClassAId)
                      ->where('storage_class_b_id', $storageClassBId);
                })->orWhere(function ($q) use ($storageClassAId, $storageClassBId): void {
                    $q->where('storage_class_a_id', $storageClassBId)
                      ->where('storage_class_b_id', $storageClassAId);
                });
            })
            ->first();

        // If no explicit rule, treat as compatible (permissive default).
        return $rule === null || $rule->is_compatible;
    }

    /**
     * Return transport regulations for a product filtered by transport mode.
     */
    public function getTransportRestrictions(int $productId, string $transportMode): Collection
    {
        return HazmatTransportRegulation::forProduct($productId)
            ->forMode($transportMode)
            ->get();
    }

    /**
     * Return all hazardous products for an organization (products with at least one hazmat classification).
     */
    public function getHazardousProducts(int $organizationId): Collection
    {
        return Product::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereHas('hazmatClassifications')
            ->with('hazmatClassifications')
            ->get();
    }

    /**
     * Determine whether a product has any hazmat classifications assigned.
     */
    public function isProductHazardous(int $productId): bool
    {
        return ProductHazmatClassification::where('product_id', $productId)->exists();
    }
}

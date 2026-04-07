<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseRequisitionLine;
use App\Models\Purchase\VendorProductPricing;
use App\Models\Purchase\VendorSourceList;
use App\Models\Sales\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SourceListService
{
    /**
     * Return the single preferred vendor for a product, or null when none is
     * configured.
     */
    public function getPreferredVendor(int $productId): ?Contact
    {
        $pricing = VendorProductPricing::forProduct($productId)
            ->preferredVendors()
            ->valid()
            ->with('vendor')
            ->first();

        if ($pricing) {
            return $pricing->vendor;
        }

        // Fall back to the highest-priority non-blocked source-list entry.
        $entry = VendorSourceList::forProduct($productId)
            ->active()
            ->byPriority()
            ->with('vendor')
            ->first();

        return $entry?->vendor;
    }

    /**
     * Return all vendors available for a product, ordered by source-list
     * priority (ascending = most preferred first).
     */
    public function getVendorsForProduct(int $productId): Collection
    {
        return VendorSourceList::forProduct($productId)
            ->active()
            ->byPriority()
            ->with(['vendor', 'pricingRecord'])
            ->get()
            ->map(fn (VendorSourceList $entry) => $entry->vendor)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Retrieve the pricing record for a specific product–vendor pair,
     * returning only currently valid records.
     */
    public function getPricingRecord(int $productId, int $vendorId): ?VendorProductPricing
    {
        return VendorProductPricing::forProduct($productId)
            ->forVendor($vendorId)
            ->valid()
            ->orderByDesc('is_preferred_vendor')
            ->first();
    }

    /**
     * Return true when the vendor appears in an active source-list entry for
     * the given product.
     */
    public function isApprovedVendor(int $productId, int $vendorId): bool
    {
        return VendorSourceList::forProduct($productId)
            ->active()
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    /**
     * Auto-select the best vendor for each product in the supplied list.
     *
     * @param  int[]  $productIds
     * @return array<int, int>  [product_id => vendor_id]
     */
    public function autoSelectVendors(array $productIds): array
    {
        $result = [];

        foreach ($productIds as $productId) {
            $vendor = $this->getPreferredVendor($productId);

            if ($vendor !== null) {
                $result[$productId] = $vendor->id;
            }
        }

        return $result;
    }

    /**
     * Suggest the best vendor for a purchase-requisition line.
     *
     * Returns vendor, pricing record, unit price, and lead time — or null
     * when no vendor could be found.
     *
     * @return array{vendor: Contact, pricing: VendorProductPricing|null, unit_price: float|null, lead_time_days: int|null}|null
     */
    public function suggestVendorForRequisitionLine(PurchaseRequisitionLine $line): ?array
    {
        $productId = $line->product_id;

        if ($productId === null) {
            return null;
        }

        $vendor = $this->getPreferredVendor($productId);

        if ($vendor === null) {
            return null;
        }

        $pricing = $this->getPricingRecord($productId, $vendor->id);

        return [
            'vendor'         => $vendor,
            'pricing'        => $pricing,
            'unit_price'     => $pricing !== null ? (float) $pricing->unit_price : null,
            'lead_time_days' => $pricing !== null ? $pricing->lead_time_days : null,
        ];
    }

    // -------------------------------------------------------------------------
    // CRUD helpers
    // -------------------------------------------------------------------------

    public function createPricingRecord(array $data): VendorProductPricing
    {
        return DB::transaction(function () use ($data): VendorProductPricing {
            // Only one preferred pricing record per product–vendor pair.
            if (!empty($data['is_preferred_vendor'])) {
                VendorProductPricing::forProduct($data['product_id'])
                    ->forVendor($data['vendor_id'])
                    ->where('is_preferred_vendor', true)
                    ->update(['is_preferred_vendor' => false]);
            }

            return VendorProductPricing::create($data);
        });
    }

    public function updatePricingRecord(VendorProductPricing $record, array $data): VendorProductPricing
    {
        return DB::transaction(function () use ($record, $data): VendorProductPricing {
            if (!empty($data['is_preferred_vendor'])) {
                VendorProductPricing::forProduct($record->product_id)
                    ->forVendor($record->vendor_id)
                    ->where('id', '!=', $record->id)
                    ->where('is_preferred_vendor', true)
                    ->update(['is_preferred_vendor' => false]);
            }

            $record->update($data);

            return $record->fresh();
        });
    }

    public function createSourceListEntry(array $data): VendorSourceList
    {
        return VendorSourceList::create($data);
    }

    public function updateSourceListEntry(VendorSourceList $entry, array $data): VendorSourceList
    {
        $entry->update($data);

        return $entry->fresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchasingInfoRecord;
use App\Models\Purchase\PurchasingInfoRecordCondition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchasingInfoRecordService
{
    /**
     * Paginated list with optional filters.
     */
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return PurchasingInfoRecord::with(['vendor', 'product', 'warehouse'])
            ->when(
                isset($filters['vendor_id']),
                fn ($q) => $q->forVendor((int) $filters['vendor_id'])
            )
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->forProduct((int) $filters['product_id'])
            )
            ->when(
                isset($filters['info_category']),
                fn ($q) => $q->where('info_category', $filters['info_category'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new purchasing info record.
     */
    public function create(array $data): PurchasingInfoRecord
    {
        return PurchasingInfoRecord::create($data);
    }

    /**
     * Update an existing purchasing info record.
     */
    public function update(PurchasingInfoRecord $record, array $data): PurchasingInfoRecord
    {
        $record->update($data);

        return $record->refresh();
    }

    /**
     * Add a time-banded pricing condition to the record.
     */
    public function addCondition(
        PurchasingInfoRecord $record,
        array $data
    ): PurchasingInfoRecordCondition {
        $data['purchasing_info_record_id'] = $record->id;
        $data['organization_id']           = $record->organization_id;

        return PurchasingInfoRecordCondition::create($data);
    }

    /**
     * Update an existing condition.
     */
    public function updateCondition(
        PurchasingInfoRecordCondition $condition,
        array $data
    ): PurchasingInfoRecordCondition {
        $condition->update($data);

        return $condition->refresh();
    }

    /**
     * Deactivate the info record (soft flag — does not delete).
     */
    public function deactivate(PurchasingInfoRecord $record): void
    {
        $record->update(['is_active' => false]);
    }

    /**
     * Resolve the effective price for a vendor–product pair on an optional date.
     * Returns null when no active info record exists.
     */
    public function getPriceForVendorProduct(
        int $vendorId,
        int $productId,
        ?string $date = null
    ): ?float {
        /** @var PurchasingInfoRecord|null $record */
        $record = PurchasingInfoRecord::active()
            ->forVendor($vendorId)
            ->forProduct($productId)
            ->with('conditions')
            ->first();

        return $record?->getEffectivePrice($date);
    }
}

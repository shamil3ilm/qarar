<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Purchase\QuotaArrangement;
use App\Models\Purchase\QuotaArrangementItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QuotaArrangementService
{
    /**
     * Paginated list with optional filters.
     */
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return QuotaArrangement::with(['product', 'warehouse', 'items.vendor'])
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->forProduct((int) $filters['product_id'])
            )
            ->when(
                isset($filters['warehouse_id']),
                fn ($q) => $q->where('warehouse_id', $filters['warehouse_id'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('valid_from', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a quota arrangement together with its items.
     * Validates that item percentages sum to exactly 100.
     *
     * @param  array{product_id: int, warehouse_id?: int, valid_from: string, valid_to?: string,
     *               is_active?: bool, notes?: string, items?: array<int, array>}  $data
     */
    public function create(array $data): QuotaArrangement
    {
        return DB::transaction(function () use ($data): QuotaArrangement {
            $items = $data['items'] ?? [];
            unset($data['items']);

            if (!empty($items)) {
                $this->assertPercentageSum($items);
            }

            $arrangement = QuotaArrangement::create($data);

            foreach ($items as $itemData) {
                $itemData['quota_arrangement_id'] = $arrangement->id;
                $itemData['organization_id']      = $arrangement->organization_id;
                QuotaArrangementItem::create($itemData);
            }

            return $arrangement->load('items.vendor');
        });
    }

    /**
     * Update arrangement header fields.
     */
    public function update(QuotaArrangement $arrangement, array $data): QuotaArrangement
    {
        $arrangement->update($data);

        return $arrangement->refresh();
    }

    /**
     * Add a new item and re-validate the percentage sum.
     */
    public function addItem(QuotaArrangement $arrangement, array $data): QuotaArrangementItem
    {
        return DB::transaction(function () use ($arrangement, $data): QuotaArrangementItem {
            $data['quota_arrangement_id'] = $arrangement->id;
            $data['organization_id']      = $arrangement->organization_id;

            $item = QuotaArrangementItem::create($data);

            $this->assertArrangementPercentageSum($arrangement);

            return $item;
        });
    }

    /**
     * Update an item and re-validate the percentage sum.
     */
    public function updateItem(QuotaArrangementItem $item, array $data): QuotaArrangementItem
    {
        return DB::transaction(function () use ($item, $data): QuotaArrangementItem {
            $item->update($data);
            $item->refresh();

            $this->assertArrangementPercentageSum($item->arrangement);

            return $item;
        });
    }

    /**
     * Remove an item and re-validate the percentage sum (if any items remain).
     */
    public function removeItem(QuotaArrangementItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $arrangement = $item->arrangement;
            $item->delete();

            // Re-load to get fresh totals; skip validation if no items remain.
            $arrangement->load('items');
            if ($arrangement->items->isNotEmpty()) {
                $this->assertArrangementPercentageSum($arrangement);
            }
        });
    }

    /**
     * Determine the best vendor source for a given product and quantity.
     * Picks the non-blocked item with the lowest quota rating, increments its
     * allocated_quantity, and returns the item so the caller can read vendor_id.
     *
     * @return QuotaArrangementItem|null  null when no active arrangement exists
     */
    public function determineSource(
        int $productId,
        float $quantity,
        ?int $warehouseId = null
    ): ?QuotaArrangementItem {
        return DB::transaction(function () use ($productId, $quantity, $warehouseId): ?QuotaArrangementItem {
            $today = now()->toDateString();

            $arrangement = QuotaArrangement::active()
                ->validOn($today)
                ->forProduct($productId)
                ->when(
                    $warehouseId !== null,
                    fn ($q) => $q->where('warehouse_id', $warehouseId)
                )
                ->with(['items' => fn ($q) => $q->active()])
                ->first();

            if ($arrangement === null || $arrangement->items->isEmpty()) {
                return null;
            }

            // Find the item with the lowest quota rating (eligible for next assignment)
            $bestItem = $arrangement->items
                ->sortBy(fn (QuotaArrangementItem $i) => $i->getQuotaRating())
                ->first();

            $bestItem->update([
                'allocated_quantity' => bcadd(
                    (string) $bestItem->allocated_quantity,
                    (string) $quantity,
                    4
                ),
                'last_assigned_at' => now(),
            ]);

            return $bestItem->refresh();
        });
    }

    /**
     * Reset allocated quantities on all items to zero.
     */
    public function resetAllocations(QuotaArrangement $arrangement): void
    {
        $arrangement->items()->update([
            'allocated_quantity' => 0,
            'last_assigned_at'   => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Assert that the provided item data rows sum to exactly 100%.
     *
     * @param  array<int, array{quota_percentage: numeric}>  $items
     */
    private function assertPercentageSum(array $items): void
    {
        $total = array_reduce(
            $items,
            fn (float $carry, array $item) => $carry + (float) ($item['quota_percentage'] ?? 0),
            0.0
        );

        if (round($total, 2) !== 100.0) {
            throw new \InvalidArgumentException(
                "Quota percentages must sum to 100. Current sum: {$total}"
            );
        }
    }

    /**
     * Assert that the persisted items of an arrangement sum to exactly 100%.
     */
    private function assertArrangementPercentageSum(QuotaArrangement $arrangement): void
    {
        $total = (float) $arrangement->items()->sum('quota_percentage');

        if (round($total, 2) !== 100.0) {
            throw new \InvalidArgumentException(
                "Quota percentages must sum to 100. Current sum: {$total}"
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Trade;

use App\Models\Trade\LandedCostCharge;
use App\Models\Trade\LandedCostItem;
use App\Models\Trade\LandedCostVoucher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LandedCostService
{
    /**
     * Create a landed cost voucher.
     */
    public function create(array $data): LandedCostVoucher
    {
        return DB::transaction(function () use ($data) {
            $voucher = LandedCostVoucher::create($data);

            if (!empty($data['items'])) {
                $this->addItems($voucher, $data['items']);
            }

            if (!empty($data['charges'])) {
                $this->addCharges($voucher, $data['charges']);
            }

            return $voucher->fresh(['items', 'charges', 'shipment']);
        });
    }

    /**
     * Add items to a voucher.
     */
    public function addItems(LandedCostVoucher $voucher, array $items): LandedCostVoucher
    {
        if (!$voucher->isEditable()) {
            throw new InvalidArgumentException('Only draft vouchers can have items added.');
        }

        return DB::transaction(function () use ($voucher, $items) {
            foreach ($items as $itemData) {
                $itemData['voucher_id'] = $voucher->id;
                LandedCostItem::create($itemData);
            }

            $voucher->recalculateTotals();

            return $voucher->fresh(['items']);
        });
    }

    /**
     * Add charges to a voucher.
     */
    public function addCharges(LandedCostVoucher $voucher, array $charges): LandedCostVoucher
    {
        if (!$voucher->isEditable()) {
            throw new InvalidArgumentException('Only draft vouchers can have charges added.');
        }

        return DB::transaction(function () use ($voucher, $charges) {
            foreach ($charges as $chargeData) {
                $chargeData['voucher_id'] = $voucher->id;

                // Calculate base amount if exchange rate is different
                if (empty($chargeData['base_amount'])) {
                    $exchangeRate = (float) ($chargeData['exchange_rate'] ?? 1);
                    $chargeData['base_amount'] = round((float) $chargeData['amount'] * $exchangeRate, 4);
                }

                LandedCostCharge::create($chargeData);
            }

            $voucher->recalculateTotals();

            return $voucher->fresh(['charges']);
        });
    }

    /**
     * Allocate charges to items based on the specified method.
     */
    public function allocate(LandedCostVoucher $voucher, string $method = 'value'): LandedCostVoucher
    {
        if (!$voucher->isEditable()) {
            throw new InvalidArgumentException('Only draft vouchers can be allocated.');
        }

        $items = $voucher->items;
        $charges = $voucher->charges;

        if ($items->isEmpty() || $charges->isEmpty()) {
            throw new InvalidArgumentException('Voucher must have both items and charges to allocate.');
        }

        return DB::transaction(function () use ($voucher, $items, $charges, $method) {
            // Group charges by type
            $chargesByType = [
                'customs_duty' => $charges->where('charge_type', LandedCostCharge::TYPE_CUSTOMS_DUTY)->sum('base_amount'),
                'freight' => $charges->where('charge_type', LandedCostCharge::TYPE_FREIGHT)->sum('base_amount'),
                'insurance' => $charges->where('charge_type', LandedCostCharge::TYPE_INSURANCE)->sum('base_amount'),
                'clearing' => $charges->where('charge_type', LandedCostCharge::TYPE_CLEARING_CHARGES)->sum('base_amount'),
            ];

            // Sum all other charge types
            $otherCharges = $charges->whereNotIn('charge_type', [
                LandedCostCharge::TYPE_CUSTOMS_DUTY,
                LandedCostCharge::TYPE_FREIGHT,
                LandedCostCharge::TYPE_INSURANCE,
                LandedCostCharge::TYPE_CLEARING_CHARGES,
            ])->sum('base_amount');

            // Calculate allocation basis per item
            $totalBasis = $this->calculateTotalBasis($items, $method);

            if ($totalBasis == 0) {
                throw new InvalidArgumentException("Total allocation basis is zero for method '{$method}'. Check item data.");
            }

            foreach ($items as $item) {
                $itemBasis = $this->getItemBasis($item, $method);
                $ratio = $itemBasis / $totalBasis;

                $item->allocated_customs_duty = round($chargesByType['customs_duty'] * $ratio, 4);
                $item->allocated_freight = round($chargesByType['freight'] * $ratio, 4);
                $item->allocated_insurance = round($chargesByType['insurance'] * $ratio, 4);
                $item->allocated_clearing = round($chargesByType['clearing'] * $ratio, 4);
                $item->allocated_other = round($otherCharges * $ratio, 4);

                $item->recalculateTotals();
                $item->save();
            }

            // Mark charges as allocated
            $voucher->charges()->update(['is_allocated' => true]);

            // Update allocation method
            $voucher->update(['allocation_method' => $method]);
            $voucher->recalculateTotals();

            return $voucher->fresh(['items', 'charges']);
        });
    }

    /**
     * Post a landed cost voucher.
     */
    public function post(LandedCostVoucher $voucher): LandedCostVoucher
    {
        if (!$voucher->canPost()) {
            throw new InvalidArgumentException('Voucher cannot be posted. Ensure it has items and charges.');
        }

        // Ensure charges are allocated
        $unallocated = $voucher->charges()->where('is_allocated', false)->count();
        if ($unallocated > 0) {
            throw new InvalidArgumentException('All charges must be allocated before posting. Run allocation first.');
        }

        return DB::transaction(function () use ($voucher) {
            $voucher->update([
                'status' => LandedCostVoucher::STATUS_POSTED,
            ]);

            return $voucher->fresh();
        });
    }

    /**
     * Cancel a landed cost voucher.
     */
    public function cancel(LandedCostVoucher $voucher): LandedCostVoucher
    {
        if (!$voucher->canCancel()) {
            throw new InvalidArgumentException('This voucher cannot be cancelled.');
        }

        return DB::transaction(function () use ($voucher) {
            $voucher->update([
                'status' => LandedCostVoucher::STATUS_CANCELLED,
            ]);

            return $voucher->fresh();
        });
    }

    /**
     * Get a voucher summary with all items and charges.
     */
    public function getVoucherSummary(LandedCostVoucher $voucher): array
    {
        $voucher->load(['items.product', 'charges.vendor', 'shipment', 'purchaseOrder']);

        return [
            'voucher' => $voucher,
            'items_summary' => [
                'count' => $voucher->items->count(),
                'total_purchase_value' => $voucher->items->sum('purchase_value'),
                'total_additional_cost' => $voucher->items->sum('total_additional_cost'),
                'total_landed_cost' => $voucher->items->sum('total_landed_cost'),
            ],
            'charges_summary' => [
                'count' => $voucher->charges->count(),
                'total_charges' => $voucher->charges->sum('base_amount'),
                'by_type' => $voucher->charges->groupBy('charge_type')->map(fn ($group) => [
                    'count' => $group->count(),
                    'total' => $group->sum('base_amount'),
                ]),
            ],
        ];
    }

    /**
     * Calculate total allocation basis.
     */
    private function calculateTotalBasis($items, string $method): float
    {
        return match ($method) {
            LandedCostVoucher::ALLOCATION_VALUE => (float) $items->sum('purchase_value'),
            LandedCostVoucher::ALLOCATION_QUANTITY => (float) $items->sum('quantity'),
            LandedCostVoucher::ALLOCATION_WEIGHT => (float) $items->sum('weight_kg'),
            LandedCostVoucher::ALLOCATION_VOLUME => (float) $items->sum('volume_cbm'),
            default => throw new InvalidArgumentException("Invalid allocation method: {$method}"),
        };
    }

    /**
     * Get allocation basis for a single item.
     */
    private function getItemBasis(LandedCostItem $item, string $method): float
    {
        return match ($method) {
            LandedCostVoucher::ALLOCATION_VALUE => (float) $item->purchase_value,
            LandedCostVoucher::ALLOCATION_QUANTITY => (float) $item->quantity,
            LandedCostVoucher::ALLOCATION_WEIGHT => (float) $item->weight_kg,
            LandedCostVoucher::ALLOCATION_VOLUME => (float) $item->volume_cbm,
            default => 0,
        };
    }
}

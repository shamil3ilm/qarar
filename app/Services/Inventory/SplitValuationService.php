<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\SplitValuation;
use App\Models\Inventory\ValuationCategory;
use App\Models\Inventory\ValuationType;
use Illuminate\Support\Facades\DB;

/**
 * Split Valuation — SAP MM equivalent (transaction MR21 / MR22 / MB1C).
 *
 * A product can be split into multiple valuation types (e.g. Domestic vs Import)
 * under a valuation category.  Each split carries its own stock quantity
 * and moving-average or standard price.
 *
 * On goods receipt:  quantity and MAP updated for the specific valuation type.
 * On goods issue:    quantity reduced; total_stock_value adjusted at MAP.
 * On price change:   revaluation delta posted (mirrors MR22 price difference account).
 */
class SplitValuationService
{
    // ----------------------------------------------------------------
    // Category management
    // ----------------------------------------------------------------

    public function createCategory(
        int $organizationId,
        int $productId,
        string $categoryCode,
        string $categoryName,
        ?string $description = null,
    ): ValuationCategory {
        return ValuationCategory::create([
            'organization_id' => $organizationId,
            'product_id'      => $productId,
            'category_code'   => strtoupper($categoryCode),
            'category_name'   => $categoryName,
            'description'     => $description,
        ]);
    }

    public function createType(
        int $organizationId,
        ValuationCategory $category,
        string $typeCode,
        string $typeName,
        ?string $description = null,
    ): ValuationType {
        return ValuationType::create([
            'organization_id'      => $organizationId,
            'valuation_category_id' => $category->id,
            'type_code'            => strtoupper($typeCode),
            'type_name'            => $typeName,
            'description'          => $description,
        ]);
    }

    // ----------------------------------------------------------------
    // Split stock read
    // ----------------------------------------------------------------

    /**
     * Return all split valuations for a product (optionally per warehouse).
     */
    public function getSplits(int $organizationId, int $productId, ?int $warehouseId = null): \Illuminate\Database\Eloquent\Collection
    {
        return SplitValuation::where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['valuationType.category', 'warehouse:id,name'])
            ->get();
    }

    // ----------------------------------------------------------------
    // Goods Receipt (MAP update)
    // ----------------------------------------------------------------

    public function goodsReceipt(
        int $organizationId,
        int $productId,
        int $valuationTypeId,
        float $quantity,
        float $unitPrice,
        string $currency,
        ?int $warehouseId = null,
    ): SplitValuation {
        return DB::transaction(function () use ($organizationId, $productId, $valuationTypeId, $quantity, $unitPrice, $currency, $warehouseId): SplitValuation {
            /** @var SplitValuation $split */
            $split = SplitValuation::firstOrCreate(
                [
                    'organization_id'   => $organizationId,
                    'product_id'        => $productId,
                    'warehouse_id'      => $warehouseId,
                    'valuation_type_id' => $valuationTypeId,
                ],
                [
                    'valuation_method'    => 'moving_average',
                    'moving_average_price' => $unitPrice,
                    'standard_price'      => $unitPrice,
                    'currency'            => $currency,
                ],
            );

            $newMap         = $split->computeNewMap($quantity, $unitPrice);
            $newQty         = (float) $split->quantity_on_hand + $quantity;
            $newStockValue  = round($newQty * $newMap, 2);

            $split->update([
                'quantity_on_hand'    => $newQty,
                'moving_average_price' => round($newMap, 6),
                'total_stock_value'   => $newStockValue,
            ]);

            return $split->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Goods Issue
    // ----------------------------------------------------------------

    public function goodsIssue(
        int $organizationId,
        int $productId,
        int $valuationTypeId,
        float $quantity,
        ?int $warehouseId = null,
    ): SplitValuation {
        return DB::transaction(function () use ($organizationId, $productId, $valuationTypeId, $quantity, $warehouseId): SplitValuation {
            /** @var SplitValuation $split */
            $split = SplitValuation::where('organization_id', $organizationId)
                ->where('product_id', $productId)
                ->where('valuation_type_id', $valuationTypeId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->lockForUpdate()
                ->firstOrFail();

            if ($split->getAvailableQuantity() < $quantity) {
                throw new \RuntimeException("Insufficient stock in valuation type {$valuationTypeId}. Available: {$split->getAvailableQuantity()}");
            }

            $newQty        = (float) $split->quantity_on_hand - $quantity;
            $newStockValue = round($newQty * (float) $split->moving_average_price, 2);

            $split->update([
                'quantity_on_hand'  => $newQty,
                'total_stock_value' => $newStockValue,
            ]);

            return $split->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Price revaluation (MR22)
    // ----------------------------------------------------------------

    /**
     * Adjust the moving-average price for a split.
     * Returns ['split' => ..., 'price_difference' => float].
     */
    public function revaluate(
        int $organizationId,
        int $productId,
        int $valuationTypeId,
        float $newPrice,
        ?int $warehouseId = null,
    ): array {
        return DB::transaction(function () use ($organizationId, $productId, $valuationTypeId, $newPrice, $warehouseId): array {
            /** @var SplitValuation $split */
            $split = SplitValuation::where('organization_id', $organizationId)
                ->where('product_id', $productId)
                ->where('valuation_type_id', $valuationTypeId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->lockForUpdate()
                ->firstOrFail();

            $oldValue     = (float) $split->total_stock_value;
            $newStockValue = round((float) $split->quantity_on_hand * $newPrice, 2);
            $priceDiff    = round($newStockValue - $oldValue, 2);

            $split->update([
                'moving_average_price' => round($newPrice, 6),
                'standard_price'       => round($newPrice, 6),
                'total_stock_value'    => $newStockValue,
            ]);

            return [
                'split'            => $split->fresh(),
                'price_difference' => $priceDiff,
            ];
        });
    }
}

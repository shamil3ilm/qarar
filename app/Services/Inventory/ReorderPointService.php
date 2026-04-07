<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReorderPointService
{
    /**
     * Return all products for an organization that are at or below their reorder level.
     *
     * Each record includes current_stock, reorder_level, shortage, and reorder_quantity.
     *
     * @return Collection<int, array{
     *   product_id: int,
     *   sku: string,
     *   name: string,
     *   current_stock: float,
     *   reorder_level: float,
     *   shortage: float,
     *   reorder_quantity: float,
     *   warehouse_id: int|null
     * }>
     */
    public function getProductsBelowReorderPoint(int $organizationId): Collection
    {
        // Aggregate stock levels per product across all warehouses
        $stockByProduct = DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('warehouses.organization_id', $organizationId)
            ->whereNull('stock_levels.deleted_at')
            ->groupBy('stock_levels.product_id')
            ->select('stock_levels.product_id', DB::raw('SUM(stock_levels.quantity_on_hand) as total_stock'))
            ->pluck('total_stock', 'product_id');

        return Product::where('organization_id', $organizationId)
            ->whereNotNull('reorder_level')
            ->where('reorder_level', '>', 0)
            ->where('track_inventory', true)
            ->get()
            ->filter(function (Product $product) use ($stockByProduct): bool {
                $stock = (float) ($stockByProduct[$product->id] ?? 0);
                return $stock <= (float) $product->reorder_level;
            })
            ->map(function (Product $product) use ($stockByProduct): array {
                $stock    = (float) ($stockByProduct[$product->id] ?? 0);
                $reorder  = (float) $product->reorder_level;
                $shortage = max(0, $reorder - $stock);

                return [
                    'product_id'       => $product->id,
                    'sku'              => $product->sku,
                    'name'             => $product->name,
                    'current_stock'    => $stock,
                    'reorder_level'    => $reorder,
                    'shortage'         => $shortage,
                    'reorder_quantity' => (float) ($product->reorder_quantity ?? $shortage),
                ];
            })
            ->values();
    }

    /**
     * Return a single product's current stock vs reorder level.
     */
    public function checkProduct(int $productId, int $organizationId): array
    {
        $product = Product::where('id', $productId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $stock = (float) DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('warehouses.organization_id', $organizationId)
            ->where('stock_levels.product_id', $productId)
            ->whereNull('stock_levels.deleted_at')
            ->sum('stock_levels.quantity_on_hand');

        $reorderLevel = (float) ($product->reorder_level ?? 0);

        return [
            'product_id'        => $product->id,
            'sku'               => $product->sku,
            'name'              => $product->name,
            'current_stock'     => $stock,
            'reorder_level'     => $reorderLevel,
            'reorder_quantity'  => (float) ($product->reorder_quantity ?? 0),
            'needs_reorder'     => $stock <= $reorderLevel && $reorderLevel > 0,
            'shortage'          => max(0, $reorderLevel - $stock),
        ];
    }
}

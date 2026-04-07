<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLevelSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateStockLevelSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int  $organizationId,
        private readonly int  $productId,
        private readonly ?int $warehouseId = null,
    ) {}

    public function handle(): void
    {
        $product = Product::where('organization_id', $this->organizationId)
            ->findOrFail($this->productId);

        $query = StockLevel::where('product_id', $this->productId)
            ->whereHas('warehouse', fn ($q) => $q->where('organization_id', $this->organizationId));

        if ($this->warehouseId !== null) {
            $query->where('warehouse_id', $this->warehouseId);
        }

        $levels = $query->get();

        foreach ($levels as $level) {
            $qtyOnHand    = (float) $level->quantity;
            $qtyReserved  = (float) ($level->reserved_quantity ?? 0);
            $qtyAvailable = max(0, $qtyOnHand - $qtyReserved);
            $reorderPoint = (float) ($level->reorder_level ?? $product->reorder_level ?? 0);
            $isLowStock   = $qtyAvailable <= $reorderPoint;

            StockLevelSnapshot::upsertForProduct(
                $this->organizationId,
                $this->productId,
                $level->warehouse_id,
                [
                    'quantity_on_hand'   => $qtyOnHand,
                    'quantity_reserved'  => $qtyReserved,
                    'quantity_available' => $qtyAvailable,
                    'reorder_point'      => $reorderPoint,
                    'is_low_stock'       => $isLowStock,
                ]
            );
        }
    }
}

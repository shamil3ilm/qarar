<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Traits\StructuredLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    use StructuredLogger;
    // Supported batch selection strategies.
    public const STRATEGY_FIFO = 'fifo';
    public const STRATEGY_LIFO = 'lifo';
    public const STRATEGY_FEFO = 'fefo';

    /**
     * Select batches to deduct from using FIFO / LIFO / FEFO ordering.
     *
     * Returns a Collection of InventoryBatch models with an additional
     * 'deduct_quantity' attribute (string, 4 decimal places) set on each
     * instance. The sum of all deduct_quantity values equals $requiredQuantity.
     *
     * @param  int         $productId
     * @param  int         $warehouseId
     * @param  float       $requiredQuantity
     * @param  string|null $strategy  'fifo'|'lifo'|'fefo' — null resolves from product
     * @return Collection<int, InventoryBatch>
     *
     * @throws InvalidArgumentException when there is insufficient batch stock.
     */
    public function selectBatchesForDeduction(
        int $productId,
        int $warehouseId,
        float $requiredQuantity,
        ?string $strategy = null
    ): Collection {
        $product  = Product::findOrFail($productId);
        $strategy = $this->resolveStrategy($product, $strategy);

        $query = InventoryBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', InventoryBatch::STATUS_AVAILABLE)
            ->whereRaw('quantity > reserved_quantity');

        $query = match ($strategy) {
            self::STRATEGY_LIFO => $query->scopeLifo($query),
            self::STRATEGY_FEFO => $query->scopeFefo($query),
            default             => $query->scopeFifo($query),
        };

        /** @var Collection<int, InventoryBatch> $batches */
        $batches = $query->get();

        $remaining     = (string) $requiredQuantity;
        $selectedBatches = new Collection();

        foreach ($batches as $batch) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = $batch->getAvailableQuantity();

            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }

            $toDeduct = bccomp($available, $remaining, 4) >= 0 ? $remaining : $available;
            $batch->setAttribute('deduct_quantity', $toDeduct);
            $selectedBatches->push($batch);
            $remaining = bcsub($remaining, $toDeduct, 4);
        }

        if (bccomp($remaining, '0', 4) > 0) {
            throw new InvalidArgumentException(
                "Insufficient batch stock for product #{$productId} in warehouse #{$warehouseId}. "
                . "Requested: {$requiredQuantity}, shortfall: {$remaining}"
            );
        }

        return $selectedBatches;
    }

    /**
     * Record a stock movement and update stock levels.
     *
     * When direction is OUT and the product has batch tracking enabled, batches
     * are auto-selected using selectBatchesForDeduction() unless a specific
     * $batchId is provided. One StockMovement is created per selected batch.
     *
     * @return StockMovement|array<int, StockMovement>  Single movement or array when split across batches.
     */
    public function recordMovement(
        int $productId,
        int $warehouseId,
        string $movementType,
        string $direction,
        float $quantity,
        float $unitCost = 0,
        ?int $variantId = null,
        ?int $locationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $fromWarehouseId = null,
        ?int $toWarehouseId = null,
        ?string $notes = null,
        ?int $createdBy = null,
        ?int $batchId = null
    ): StockMovement|array {
        return DB::transaction(function () use (
            $productId, $warehouseId, $movementType, $direction, $quantity,
            $unitCost, $variantId, $locationId, $referenceType, $referenceId,
            $referenceNumber, $fromWarehouseId, $toWarehouseId, $notes, $createdBy,
            $batchId
        ) {
            $product = Product::findOrFail($productId);

            // For non-inventory-tracked products: record the movement log for
            // audit purposes but skip all stock level adjustments.
            if (!$product->track_inventory) {
                return StockMovement::create([
                    'organization_id'  => $product->organization_id,
                    'product_id'       => $productId,
                    'variant_id'       => $variantId,
                    'warehouse_id'     => $warehouseId,
                    'location_id'      => $locationId,
                    'movement_type'    => $movementType,
                    'direction'        => $direction,
                    'quantity'         => $quantity,
                    'unit_cost'        => $unitCost,
                    'total_cost'       => bcmul((string) $quantity, (string) $unitCost, 4),
                    'balance_after'    => 0,
                    'reference_type'   => $referenceType,
                    'reference_id'     => $referenceId,
                    'reference_number' => $referenceNumber,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id'  => $toWarehouseId,
                    'notes'            => $notes,
                    'created_by'       => $createdBy ?? auth()->id(),
                ]);
            }

            // Handle batch-tracked outbound movements.
            if (
                $direction === StockMovement::DIRECTION_OUT
                && $product->track_batches
            ) {
                return $this->recordBatchedMovement(
                    product: $product,
                    warehouseId: $warehouseId,
                    movementType: $movementType,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    variantId: $variantId,
                    locationId: $locationId,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    referenceNumber: $referenceNumber,
                    fromWarehouseId: $fromWarehouseId,
                    toWarehouseId: $toWarehouseId,
                    notes: $notes,
                    createdBy: $createdBy,
                    batchId: $batchId
                );
            }

            return $this->recordSingleMovement(
                product: $product,
                warehouseId: $warehouseId,
                movementType: $movementType,
                direction: $direction,
                quantity: $quantity,
                unitCost: $unitCost,
                variantId: $variantId,
                locationId: $locationId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                fromWarehouseId: $fromWarehouseId,
                toWarehouseId: $toWarehouseId,
                notes: $notes,
                createdBy: $createdBy
            );
        });
    }

    /**
     * Record a purchase receipt (goods in).
     */
    public function recordPurchase(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        ?int $variantId = null,
        ?string $referenceNumber = null,
        ?int $referenceId = null
    ): StockMovement {
        $movement = $this->recordMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            movementType: StockMovement::TYPE_PURCHASE,
            direction: StockMovement::DIRECTION_IN,
            quantity: $quantity,
            unitCost: $unitCost,
            variantId: $variantId,
            referenceType: 'bill',
            referenceId: $referenceId,
            referenceNumber: $referenceNumber
        );

        // recordMovement returns array only for batched OUTbound; purchase is
        // always a single inbound movement.
        return is_array($movement) ? $movement[0] : $movement;
    }

    /**
     * Record a sale (goods out).
     */
    public function recordSale(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?int $variantId = null,
        ?string $referenceNumber = null,
        ?int $referenceId = null
    ): StockMovement|array {
        $stockLevel = $this->getStockLevel($productId, $warehouseId, $variantId);
        $unitCost   = $stockLevel?->average_cost ?? 0;

        $product = Product::findOrFail($productId);
        if ($product->track_batches && $product->has_expiry) {
            $availableNonExpired = InventoryBatch::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', 'available')
                ->where(function ($q): void {
                    $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                })
                ->sum('quantity');
            if (bccomp((string) $quantity, (string) $availableNonExpired, 4) > 0) {
                throw new \App\Exceptions\ApiException(
                    "Insufficient non-expired stock available. Available: {$availableNonExpired}, Requested: {$quantity}"
                );
            }
        }

        return $this->recordMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            movementType: StockMovement::TYPE_SALE,
            direction: StockMovement::DIRECTION_OUT,
            quantity: $quantity,
            unitCost: (float) $unitCost,
            variantId: $variantId,
            referenceType: 'invoice',
            referenceId: $referenceId,
            referenceNumber: $referenceNumber
        );
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?int $variantId = null,
        ?string $referenceNumber = null,
        ?int $referenceId = null
    ): array {
        return DB::transaction(function () use (
            $productId, $fromWarehouseId, $toWarehouseId,
            $quantity, $variantId, $referenceNumber, $referenceId
        ) {
            $stockLevel = $this->getStockLevel($productId, $fromWarehouseId, $variantId);
            $unitCost   = $stockLevel?->average_cost ?? 0;

            // Record transfer out (may return array for batch-tracked products)
            $outMovement = $this->recordMovement(
                productId: $productId,
                warehouseId: $fromWarehouseId,
                movementType: StockMovement::TYPE_TRANSFER_OUT,
                direction: StockMovement::DIRECTION_OUT,
                quantity: $quantity,
                unitCost: (float) $unitCost,
                variantId: $variantId,
                referenceType: 'stock_transfer',
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                toWarehouseId: $toWarehouseId
            );

            // Record transfer in
            $inMovement = $this->recordMovement(
                productId: $productId,
                warehouseId: $toWarehouseId,
                movementType: StockMovement::TYPE_TRANSFER_IN,
                direction: StockMovement::DIRECTION_IN,
                quantity: $quantity,
                unitCost: (float) $unitCost,
                variantId: $variantId,
                referenceType: 'stock_transfer',
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                fromWarehouseId: $fromWarehouseId
            );

            return [
                'out' => $outMovement,
                'in'  => $inMovement,
            ];
        });
    }

    /**
     * Adjust stock quantity (for corrections, damage, etc.).
     */
    public function adjust(
        int $productId,
        int $warehouseId,
        float $newQuantity,
        ?int $variantId = null,
        ?int $locationId = null,
        ?string $referenceNumber = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): ?StockMovement {
        $stockLevel      = $this->getStockLevel($productId, $warehouseId, $variantId, $locationId);
        $currentQuantity = $stockLevel?->quantity ?? 0;
        $difference      = bcsub((string) $newQuantity, (string) $currentQuantity, 4);

        if (bccomp($difference, '0', 4) === 0) {
            return null; // No adjustment needed
        }

        $direction = bccomp($difference, '0', 4) > 0
            ? StockMovement::DIRECTION_IN
            : StockMovement::DIRECTION_OUT;

        $movement = $this->recordMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            movementType: StockMovement::TYPE_ADJUSTMENT,
            direction: $direction,
            quantity: abs((float) $difference),
            unitCost: (float) ($stockLevel?->average_cost ?? 0),
            variantId: $variantId,
            locationId: $locationId,
            referenceType: 'stock_adjustment',
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            notes: $notes
        );

        return is_array($movement) ? $movement[0] : $movement;
    }

    /**
     * Reserve stock for an order.
     *
     * Uses a pessimistic lock so that the availability check and the subsequent
     * increment are atomic — preventing double-reservation under concurrent load.
     */
    public function reserve(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?int $variantId = null
    ): bool {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $variantId) {
            $stockLevel = StockLevel::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                return false;
            }

            return $stockLevel->reserve($quantity);
        });
    }

    /**
     * Release reserved stock.
     *
     * Uses a pessimistic lock so that the read and decrement are atomic.
     */
    public function release(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?int $variantId = null
    ): void {
        DB::transaction(function () use ($productId, $warehouseId, $quantity, $variantId) {
            $stockLevel = StockLevel::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($stockLevel) {
                $stockLevel->release($quantity);
            }
        });
    }

    /**
     * Get stock level for a product/warehouse combination.
     */
    public function getStockLevel(
        int $productId,
        int $warehouseId,
        ?int $variantId = null,
        ?int $locationId = null
    ): ?StockLevel {
        return StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('variant_id', $variantId)
            ->where('location_id', $locationId)
            ->first();
    }

    /**
     * Get or create a stock level record.
     *
     * When called inside a DB::transaction() with lockForUpdate() intent the
     * caller must pass $lock = true so that the SELECT is promoted to
     * SELECT … FOR UPDATE, preventing concurrent read-modify-write races.
     */
    public function getOrCreateStockLevel(
        int $productId,
        int $warehouseId,
        ?int $variantId = null,
        ?int $locationId = null,
        bool $lock = false
    ): StockLevel {
        $product = Product::findOrFail($productId);

        // Try to fetch the existing row first (with optional pessimistic lock).
        $query = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('variant_id', $variantId)
            ->where('location_id', $locationId);

        if ($lock) {
            $query = $query->lockForUpdate();
        }

        $stockLevel = $query->first();

        if (!$stockLevel) {
            try {
                $stockLevel = StockLevel::create([
                    'organization_id'   => $product->organization_id,
                    'product_id'        => $productId,
                    'warehouse_id'      => $warehouseId,
                    'variant_id'        => $variantId,
                    'location_id'       => $locationId,
                    'quantity'          => 0,
                    'reserved_quantity' => 0,
                    'average_cost'      => 0,
                    'total_value'       => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another concurrent request inserted the row between our SELECT and INSERT.
                // Re-fetch the now-existing record (with lock if requested).
                $refetch = StockLevel::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('variant_id', $variantId)
                    ->where('location_id', $locationId);

                if ($lock) {
                    $refetch = $refetch->lockForUpdate();
                }

                $stockLevel = $refetch->firstOrFail();
            }
        }

        return $stockLevel;
    }

    /**
     * Get total stock for a product across all warehouses.
     */
    public function getTotalStock(int $productId, ?int $variantId = null): float
    {
        return StockLevel::where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->sum('quantity');
    }

    /**
     * Get available stock for a product across all warehouses.
     */
    public function getAvailableStock(int $productId, ?int $variantId = null): float
    {
        return StockLevel::where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->selectRaw('SUM(quantity - reserved_quantity) as available')
            ->value('available') ?? 0;
    }

    /**
     * Check if product has sufficient stock.
     */
    public function hasAvailableStock(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?int $variantId = null
    ): bool {
        $stockLevel = $this->getStockLevel($productId, $warehouseId, $variantId);

        if (!$stockLevel) {
            return false;
        }

        return $stockLevel->hasAvailable($quantity);
    }

    /**
     * Get products that need reordering.
     */
    public function getLowStockProducts(?int $warehouseId = null, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return StockLevel::with(['product:id,name,sku,reorder_point', 'warehouse:id,name'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->lowStock()
            ->paginate($perPage);
    }

    /**
     * Get stock valuation report.
     * Totals are computed via DB aggregation (no full collection load).
     * Items are paginated to cap memory per request.
     *
     * @return array{items: \Illuminate\Contracts\Pagination\LengthAwarePaginator, totals: array}
     */
    public function getStockValuation(?int $warehouseId = null, int $perPage = 25): array
    {
        $base = StockLevel::where('quantity', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $agg = (clone $base)->selectRaw(
            'COUNT(*) as total_items, '
            . 'COALESCE(SUM(quantity), 0) as total_quantity, '
            . 'COALESCE(SUM(total_value), 0) as total_value'
        )->first();

        $paginator = (clone $base)
            ->with(['product:id,name', 'warehouse:id,name'])
            ->paginate($perPage);

        return [
            'items'  => $paginator,
            'totals' => [
                'total_items'    => (int)   ($agg->total_items    ?? 0),
                'total_quantity' => (float) ($agg->total_quantity ?? 0),
                'total_value'    => (float) ($agg->total_value    ?? 0),
            ],
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the batch selection strategy for a product.
     *
     * Priority: explicit argument > product.batch_selection_strategy > product.costing_method.
     */
    private function resolveStrategy(Product $product, ?string $strategy): string
    {
        if ($strategy !== null && in_array($strategy, [self::STRATEGY_FIFO, self::STRATEGY_LIFO, self::STRATEGY_FEFO], true)) {
            return $strategy;
        }

        $productStrategy = $product->batch_selection_strategy;

        if ($productStrategy !== null && in_array($productStrategy, [self::STRATEGY_FIFO, self::STRATEGY_LIFO, self::STRATEGY_FEFO], true)) {
            return $productStrategy;
        }

        // Fall back to costing_method: fifo maps directly; lifo maps directly; anything else => fifo.
        return match ($product->costing_method) {
            Product::COSTING_FIFO => self::STRATEGY_FIFO,
            'lifo'                => self::STRATEGY_LIFO,
            default               => self::STRATEGY_FIFO,
        };
    }

    /**
     * Handle an outbound movement for a batch-tracked product.
     *
     * If a specific $batchId is provided, deduct from that batch only.
     * Otherwise, auto-select batches using selectBatchesForDeduction().
     *
     * @return array<int, StockMovement>
     */
    private function recordBatchedMovement(
        Product $product,
        int $warehouseId,
        string $movementType,
        float $quantity,
        float $unitCost,
        ?int $variantId,
        ?int $locationId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $referenceNumber,
        ?int $fromWarehouseId,
        ?int $toWarehouseId,
        ?string $notes,
        ?int $createdBy,
        ?int $batchId
    ): array {
        if ($batchId !== null) {
            $batch = InventoryBatch::findOrFail($batchId);
            $batch->setAttribute('deduct_quantity', (string) $quantity);
            $batches = new Collection([$batch]);
        } else {
            $batches = $this->selectBatchesForDeduction(
                productId: $product->id,
                warehouseId: $warehouseId,
                requiredQuantity: $quantity,
            );
        }

        $movements = [];

        foreach ($batches as $batch) {
            $deductQty = (float) $batch->getAttribute('deduct_quantity');

            // Deduct from the batch record itself
            $batch->deduct((string) $deductQty);

            // Record the stock-level movement for this batch slice
            $movement = $this->recordSingleMovement(
                product: $product,
                warehouseId: $warehouseId,
                movementType: $movementType,
                direction: StockMovement::DIRECTION_OUT,
                quantity: $deductQty,
                unitCost: $unitCost > 0 ? $unitCost : (float) $batch->unit_cost,
                variantId: $variantId,
                locationId: $locationId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                fromWarehouseId: $fromWarehouseId,
                toWarehouseId: $toWarehouseId,
                notes: $notes !== null ? "{$notes} [batch: {$batch->batch_number}]" : "batch: {$batch->batch_number}",
                createdBy: $createdBy
            );

            $movements[] = $movement;
        }

        return $movements;
    }

    /**
     * Core single-movement recorder: adjusts StockLevel and inserts StockMovement row.
     */
    private function recordSingleMovement(
        Product $product,
        int $warehouseId,
        string $movementType,
        string $direction,
        float $quantity,
        float $unitCost,
        ?int $variantId,
        ?int $locationId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $referenceNumber,
        ?int $fromWarehouseId,
        ?int $toWarehouseId,
        ?string $notes,
        ?int $createdBy
    ): StockMovement {
        // Get or create stock level with a pessimistic lock to prevent
        // concurrent read-modify-write races on the quantity column.
        $stockLevel = $this->getOrCreateStockLevel(
            $product->id,
            $warehouseId,
            $variantId,
            $locationId,
            lock: true
        );

        // Validate outgoing stock
        if ($direction === StockMovement::DIRECTION_OUT) {
            $warehouse = Warehouse::findOrFail($warehouseId);
            if (!$warehouse->allow_negative_stock && $stockLevel->quantity < $quantity) {
                throw new InvalidArgumentException(
                    "Insufficient stock. Available: {$stockLevel->quantity}, Requested: {$quantity}"
                );
            }
        }

        // Update stock quantity
        $newQuantity = $direction === StockMovement::DIRECTION_IN
            ? bcadd((string) $stockLevel->quantity, (string) $quantity, 4)
            : bcsub((string) $stockLevel->quantity, (string) $quantity, 4);

        // Update average cost for incoming movements
        if ($direction === StockMovement::DIRECTION_IN && $unitCost > 0) {
            $this->updateAverageCost($stockLevel, $quantity, $unitCost);
        }

        $stockLevel->quantity = $newQuantity;
        $stockLevel->last_purchase_price = $direction === StockMovement::DIRECTION_IN
            ? $unitCost
            : $stockLevel->last_purchase_price;
        $stockLevel->recalculateTotalValue();
        $stockLevel->save();

        return StockMovement::create([
            'organization_id'   => $stockLevel->organization_id,
            'product_id'        => $product->id,
            'variant_id'        => $variantId,
            'warehouse_id'      => $warehouseId,
            'location_id'       => $locationId,
            'movement_type'     => $movementType,
            'direction'         => $direction,
            'quantity'          => $quantity,
            'unit_cost'         => $unitCost,
            'total_cost'        => bcmul((string) $quantity, (string) $unitCost, 4),
            'balance_after'     => $newQuantity,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'reference_number'  => $referenceNumber,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id'   => $toWarehouseId,
            'notes'             => $notes,
            'created_by'        => $createdBy ?? auth()->id(),
        ]);
    }

    /**
     * Update average cost using weighted average method.
     */
    private function updateAverageCost(StockLevel $stockLevel, float $newQuantity, float $newUnitCost): void
    {
        $currentQuantity = (float) $stockLevel->quantity;
        $currentCost     = (float) $stockLevel->average_cost;

        if ($currentQuantity <= 0) {
            $stockLevel->average_cost = $newUnitCost;
            return;
        }

        // Weighted average: ((current_qty * current_cost) + (new_qty * new_cost)) / (current_qty + new_qty)
        $totalValue = bcadd(
            bcmul((string) $currentQuantity, (string) $currentCost, 4),
            bcmul((string) $newQuantity, (string) $newUnitCost, 4),
            4
        );

        $totalQuantity = bcadd((string) $currentQuantity, (string) $newQuantity, 4);

        if (bccomp($totalQuantity, '0', 4) <= 0) {
            return;
        }

        $stockLevel->average_cost = bcdiv($totalValue, $totalQuantity, 4);
    }
}

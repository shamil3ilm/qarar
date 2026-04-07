<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevelSnapshot extends Model
{
    protected $fillable = [
        'organization_id',
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_available',
        'reorder_point',
        'is_low_stock',
        'computed_at',
    ];

    protected $casts = [
        'quantity_on_hand'   => 'decimal:4',
        'quantity_reserved'  => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'reorder_point'      => 'decimal:4',
        'is_low_stock'       => 'boolean',
        'computed_at'        => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Upsert the snapshot for a product+warehouse.
     */
    public static function upsertForProduct(int $orgId, int $productId, ?int $warehouseId, array $data): void
    {
        static::updateOrCreate(
            ['organization_id' => $orgId, 'product_id' => $productId, 'warehouse_id' => $warehouseId],
            array_merge($data, ['computed_at' => now()])
        );
    }
}

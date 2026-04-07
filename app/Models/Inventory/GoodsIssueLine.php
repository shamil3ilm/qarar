<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsIssueLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:4',
            'unit_cost'   => 'decimal:4',
            'total_value' => 'decimal:4',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }
}

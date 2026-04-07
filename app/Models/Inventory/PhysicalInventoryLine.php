<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalInventoryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'product_id',
        'variant_id',
        'warehouse_location_id',
        'book_quantity',
        'counted_quantity',
        'difference_quantity',
        'unit_cost',
        'difference_value',
        'adjustment_status',
    ];

    protected function casts(): array
    {
        return [
            'book_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'difference_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'difference_value' => 'decimal:4',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PhysicalInventoryDocument::class, 'document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function hasDifference(): bool
    {
        return $this->difference_quantity !== null && (float) $this->difference_quantity !== 0.0;
    }

    public function isAdjusted(): bool
    {
        return $this->adjustment_status === 'adjusted';
    }

    public function isPending(): bool
    {
        return $this->adjustment_status === 'pending';
    }

    public function recalculateDifference(): void
    {
        if ($this->counted_quantity === null) {
            return;
        }

        $diff = bcsub((string) $this->counted_quantity, (string) $this->book_quantity, 4);
        $this->difference_quantity = $diff;

        if ($this->unit_cost !== null) {
            $this->difference_value = bcmul($diff, (string) $this->unit_cost, 4);
        }
    }

    public function scopePending($query)
    {
        return $query->where('adjustment_status', 'pending');
    }

    public function scopeWithDifferences($query)
    {
        return $query->whereNotNull('difference_quantity')
            ->where('difference_quantity', '!=', 0);
    }
}

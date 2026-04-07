<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferOrderItem extends Model
{
    use HasFactory;

    public const STATUS_OPEN                 = 'open';
    public const STATUS_PARTIALLY_TRANSFERRED = 'partially_transferred';
    public const STATUS_TRANSFERRED          = 'transferred';
    public const STATUS_CANCELLED            = 'cancelled';

    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'variant_id',
        'source_location_id',
        'dest_location_id',
        'requested_quantity',
        'transferred_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity'   => 'decimal:4',
            'transferred_quantity' => 'decimal:4',
        ];
    }

    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransferOrder::class, 'transfer_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }

    public function destLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'dest_location_id');
    }

    public function getRemainingQuantity(): float
    {
        return max(0.0, (float) $this->requested_quantity - (float) $this->transferred_quantity);
    }

    public function isFullyTransferred(): bool
    {
        return (float) $this->transferred_quantity >= (float) $this->requested_quantity;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}

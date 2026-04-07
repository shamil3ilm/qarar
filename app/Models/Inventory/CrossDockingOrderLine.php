<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossDockingOrderLine extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'cross_docking_order_id',
        'product_id',
        'quantity',
        'unit_id',
        'quantity_transferred',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity'             => 'decimal:4',
            'quantity_transferred' => 'decimal:4',
        ];
    }

    public function crossDockingOrder(): BelongsTo
    {
        return $this->belongsTo(CrossDockingOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function getRemainingQuantity(): float
    {
        return max(0, (float) bcsub((string) $this->quantity, (string) $this->quantity_transferred, 4));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isTransferred(): bool
    {
        return $this->status === self::STATUS_TRANSFERRED;
    }

    public function isPartial(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeNotCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL]);
    }
}

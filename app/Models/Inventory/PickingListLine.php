<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\WarehouseLocation;

class PickingListLine extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED   = 'skipped';

    protected $fillable = [
        'picking_list_id',
        'source_type',
        'source_id',
        'product_id',
        'variant_id',
        'from_location_id',
        'to_location_id',
        'required_quantity',
        'picked_quantity',
        'status',
        'picked_at',
        'picked_by',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'picked_quantity'   => 'decimal:4',
            'source_id'         => 'integer',
            'sort_order'        => 'integer',
            'picked_at'         => 'datetime',
        ];
    }

    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    /**
     * Record a pick quantity against this line.
     * Accumulates picked_quantity and updates status accordingly.
     */
    public function pick(float $quantity, int $userId): self
    {
        $newPickedQty = (float) $this->picked_quantity + $quantity;

        if ($newPickedQty >= (float) $this->required_quantity) {
            $newPickedQty = (float) $this->required_quantity;
            $newStatus    = self::STATUS_COMPLETED;
        } else {
            $newStatus = self::STATUS_PARTIAL;
        }

        $this->picked_quantity = $newPickedQty;
        $this->status          = $newStatus;
        $this->picked_at       = now();
        $this->picked_by       = $userId;
        $this->save();

        return $this;
    }

    public function skip(string $notes = ''): self
    {
        $this->status = self::STATUS_SKIPPED;
        $this->notes  = $notes ?: $this->notes;
        $this->save();

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOrderedByLocation($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackorderRecord extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'sales_order_id',
        'sales_order_line_id',
        'product_id',
        'original_quantity',
        'backordered_quantity',
        'fulfilled_quantity',
        'status',
        'original_delivery_date',
        'rescheduled_delivery_date',
        'reason',
        'priority',
    ];

    protected $casts = [
        'original_quantity' => 'decimal:4',
        'backordered_quantity' => 'decimal:4',
        'fulfilled_quantity' => 'decimal:4',
        'original_delivery_date' => 'date',
        'rescheduled_delivery_date' => 'date',
        'priority' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('created_at');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getRemainingQuantity(): float
    {
        return (float) ($this->backordered_quantity - $this->fulfilled_quantity);
    }

    public function isFullyFulfilled(): bool
    {
        return (float) $this->fulfilled_quantity >= (float) $this->backordered_quantity;
    }
}

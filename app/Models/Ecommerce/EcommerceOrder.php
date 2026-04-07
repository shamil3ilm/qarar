<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrder extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $guarded = ['id'];

    // Order status values
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    // Financial status values
    public const FINANCIAL_PENDING = 'pending';
    public const FINANCIAL_PAID = 'paid';
    public const FINANCIAL_PARTIALLY_PAID = 'partially_paid';
    public const FINANCIAL_REFUNDED = 'refunded';

    // Fulfillment status values
    public const FULFILLMENT_UNFULFILLED = 'unfulfilled';
    public const FULFILLMENT_PARTIAL = 'partial';
    public const FULFILLMENT_FULFILLED = 'fulfilled';

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'raw_data' => 'array',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
            'ordered_at' => 'datetime',
        ];
    }

    // Relationships

    public function channel(): BelongsTo
    {
        return $this->belongsTo(EcommerceChannel::class, 'channel_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'order_id');
    }

    // Scopes

    public function scopeByChannel(Builder $query, int $channelId): Builder
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('is_processed', false);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfillment_status === self::FULFILLMENT_FULFILLED;
    }

    public function canBeProcessed(): bool
    {
        return !$this->is_processed
            && !in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REFUNDED]);
    }

    public function canBeFulfilled(): bool
    {
        return $this->is_processed
            && !in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REFUNDED, self::STATUS_DELIVERED])
            && $this->fulfillment_status !== self::FULFILLMENT_FULFILLED;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdPartyOrder extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PO_CREATED = 'po_created';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'sales_order_id',
        'vendor_id',
        'purchase_order_id',
        'status',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_country_code',
        'vendor_reference',
        'shipping_confirmation',
        'estimated_delivery_date',
        'actual_delivery_date',
        'notes',
    ];

    protected $casts = [
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class, 'vendor_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ThirdPartyOrderLine::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForSalesOrder(Builder $query, int $salesOrderId): Builder
    {
        return $query->where('sales_order_id', $salesOrderId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function canCreatePO(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->purchase_order_id === null;
    }

    public function hasShipped(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_DELIVERED, self::STATUS_INVOICED], true);
    }
}

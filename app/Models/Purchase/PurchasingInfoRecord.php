<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchasingInfoRecord extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'product_id',
        'warehouse_id',
        'info_category',
        'is_active',
        'planned_delivery_days',
        'reminder_days',
        'overdelivery_tolerance',
        'underdelivery_tolerance',
        'is_underdelivery_tolerated',
        'net_price',
        'price_unit',
        'currency_code',
        'minimum_order_quantity',
        'standard_order_quantity',
        'last_purchase_date',
        'last_purchase_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                  => 'boolean',
            'is_underdelivery_tolerated' => 'boolean',
            'net_price'                  => 'decimal:4',
            'last_purchase_price'        => 'decimal:4',
            'minimum_order_quantity'     => 'decimal:4',
            'standard_order_quantity'    => 'decimal:4',
            'overdelivery_tolerance'     => 'decimal:2',
            'underdelivery_tolerance'    => 'decimal:2',
            'last_purchase_date'         => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PurchasingInfoRecordCondition::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the condition valid today (or on a given date).
     */
    public function getActiveCondition(?string $date = null): ?PurchasingInfoRecordCondition
    {
        $checkDate = $date ?? now()->toDateString();

        return $this->conditions()
            ->active()
            ->validOn($checkDate)
            ->orderBy('valid_from', 'desc')
            ->first();
    }

    /**
     * Return the effective price: from an active condition if one exists,
     * otherwise fall back to the record-level net_price.
     */
    public function getEffectivePrice(?string $date = null): ?float
    {
        $condition = $this->getActiveCondition($date);

        if ($condition !== null) {
            return (float) $condition->net_price;
        }

        return $this->net_price !== null ? (float) $this->net_price : null;
    }
}

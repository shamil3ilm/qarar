<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Stores the negotiated price, lead time, and order terms for a specific
 * (organization, vendor, product) combination. Equivalent to SAP PIR.
 */
class VendorProductPricing extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'vendor_product_pricing';

    protected $fillable = [
        'organization_id',
        'product_id',
        'vendor_id',
        'vendor_product_code',
        'vendor_product_description',
        'unit_price',
        'currency_code',
        'lead_time_days',
        'minimum_order_quantity',
        'order_quantity_multiple',
        'valid_from',
        'valid_to',
        'is_preferred_vendor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'              => 'decimal:4',
            'minimum_order_quantity'  => 'decimal:4',
            'order_quantity_multiple' => 'decimal:4',
            'lead_time_days'          => 'integer',
            'valid_from'              => 'date',
            'valid_to'                => 'date',
            'is_preferred_vendor'     => 'boolean',
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

    public function vendorSourceListEntries(): HasMany
    {
        return $this->hasMany(VendorSourceList::class, 'vendor_product_pricing_id');
    }

    // -------------------------------------------------------------------------
    // Business Methods
    // -------------------------------------------------------------------------

    /**
     * Whether this pricing record is currently within its validity window.
     */
    public function isValid(): bool
    {
        $today = Carbon::today();

        if ($this->valid_from !== null && $today->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to !== null && $today->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    /**
     * Return the unit price (caller applies exchange rate if currencies differ).
     */
    public function getUnitPrice(string $currency): float
    {
        return (float) $this->unit_price;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePreferredVendors($query)
    {
        return $query->where('is_preferred_vendor', true);
    }

    public function scopeValid($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today): void {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
        })->where(function ($q) use ($today): void {
            $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
        });
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}

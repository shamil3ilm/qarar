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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Ordered list of approved vendors per product. Supports fixed-vendor locks,
 * vendor blocks, and proportional volume splits via quota percentages.
 * Equivalent to SAP Source Lists.
 */
class VendorSourceList extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'vendor_source_lists';

    protected $fillable = [
        'organization_id',
        'product_id',
        'vendor_id',
        'vendor_product_pricing_id',
        'plant_code',
        'valid_from',
        'valid_to',
        'is_fixed_vendor',
        'is_blocked',
        'priority',
        'quota_percentage',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'       => 'date',
            'valid_to'         => 'date',
            'is_fixed_vendor'  => 'boolean',
            'is_blocked'       => 'boolean',
            'priority'         => 'integer',
            'quota_percentage' => 'decimal:2',
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

    public function pricingRecord(): BelongsTo
    {
        return $this->belongsTo(VendorProductPricing::class, 'vendor_product_pricing_id');
    }

    // -------------------------------------------------------------------------
    // Business Methods
    // -------------------------------------------------------------------------

    /**
     * Whether this entry is active (not blocked and within validity window).
     */
    public function isActive(): bool
    {
        if ($this->is_blocked) {
            return false;
        }

        $today = Carbon::today();

        if ($this->valid_from !== null && $today->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to !== null && $today->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        $today = now()->toDateString();

        return $query->where('is_blocked', false)
            ->where(function ($q) use ($today): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }
}

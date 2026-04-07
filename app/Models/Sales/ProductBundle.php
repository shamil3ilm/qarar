<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBundle extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'sku', 'description', 'image_path', 'pricing_type',
        'bundle_price', 'discount_percent', 'original_total', 'savings_amount',
        'available_from', 'available_until', 'is_limited_time', 'max_quantity',
        'sold_quantity', 'min_order_quantity', 'max_order_quantity',
        'eligible_customer_tiers', 'is_featured', 'is_active', 'display_order',
    ];

    protected $casts = [
        'bundle_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'original_total' => 'decimal:2',
        'savings_amount' => 'decimal:2',
        'available_from' => 'date',
        'available_until' => 'date',
        'eligible_customer_tiers' => 'array',
        'is_limited_time' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }
}

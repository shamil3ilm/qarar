<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_id', 'product_id', 'variant_id', 'quantity', 'description',
        'original_price', 'unit_price', 'bundle_price', 'discount_percentage',
        'is_optional', 'is_default_selected', 'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'original_price' => 'decimal:4',
        'bundle_price' => 'decimal:4',
        'is_optional' => 'boolean',
        'is_default_selected' => 'boolean',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }
}

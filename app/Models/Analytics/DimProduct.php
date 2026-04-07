<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimProduct extends Model
{
    protected $table = 'dim_product';

    protected $fillable = [
        'organization_id',
        'product_id',
        'product_code',
        'product_name',
        'category_name',
        'subcategory_name',
        'unit_of_measure',
        'product_type',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'synced_at'   => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function factSales(): HasMany
    {
        return $this->hasMany(FactSale::class);
    }

    public function factPurchases(): HasMany
    {
        return $this->hasMany(FactPurchase::class);
    }

    public function factInventoryMovements(): HasMany
    {
        return $this->hasMany(FactInventoryMovement::class);
    }
}

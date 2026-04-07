<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimWarehouse extends Model
{
    protected $table = 'dim_warehouse';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'warehouse_code',
        'warehouse_name',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

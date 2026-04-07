<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactInventoryMovement extends Model
{
    protected $table = 'fact_inventory_movements';

    protected $fillable = [
        'organization_id',
        'dim_product_id',
        'dim_warehouse_id',
        'dim_time_id',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'quantity_balance',
        'unit_cost',
        'total_cost',
        'currency_code',
        'reference_type',
    ];

    protected $casts = [
        'quantity_in'      => 'decimal:4',
        'quantity_out'     => 'decimal:4',
        'quantity_balance' => 'decimal:4',
        'unit_cost'        => 'decimal:4',
        'total_cost'       => 'decimal:4',
    ];

    public function dimProduct(): BelongsTo
    {
        return $this->belongsTo(DimProduct::class);
    }

    public function dimWarehouse(): BelongsTo
    {
        return $this->belongsTo(DimWarehouse::class);
    }

    public function dimTime(): BelongsTo
    {
        return $this->belongsTo(DimTime::class);
    }
}

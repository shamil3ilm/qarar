<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimTime extends Model
{
    protected $table = 'dim_time';

    protected $fillable = [
        'full_date',
        'day_of_week',
        'day_name',
        'day_of_month',
        'week_of_year',
        'month_number',
        'month_name',
        'quarter',
        'year',
        'fiscal_year',
        'fiscal_period',
        'is_weekend',
        'is_holiday',
    ];

    protected $casts = [
        'full_date'    => 'date',
        'is_weekend'   => 'boolean',
        'is_holiday'   => 'boolean',
        'day_of_week'  => 'integer',
        'day_of_month' => 'integer',
        'week_of_year' => 'integer',
        'month_number' => 'integer',
        'quarter'      => 'integer',
        'year'         => 'integer',
        'fiscal_year'  => 'integer',
        'fiscal_period' => 'integer',
    ];

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

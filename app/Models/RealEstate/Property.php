<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_properties';

    protected $fillable = [
        'organization_id',
        'portfolio_id',
        'code',
        'name',
        'type',
        'street_address',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'total_area_sqm',
        'land_area_sqm',
        'current_valuation',
        'valuation_currency',
        'valuation_date',
        'ownership_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_area_sqm' => 'decimal:4',
        'land_area_sqm' => 'decimal:4',
        'current_valuation' => 'decimal:4',
        'valuation_date' => 'date',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class, 'portfolio_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'property_id');
    }

    public function serviceChargeSettlements(): HasMany
    {
        return $this->hasMany(ServiceChargeSettlement::class, 'property_id');
    }
}

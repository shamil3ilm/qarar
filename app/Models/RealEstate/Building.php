<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_buildings';

    protected $fillable = [
        'organization_id',
        'property_id',
        'code',
        'name',
        'floors_above_ground',
        'floors_below_ground',
        'gross_area_sqm',
        'net_lettable_area_sqm',
        'year_built',
        'construction_type',
        'status',
    ];

    protected $casts = [
        'floors_above_ground' => 'integer',
        'floors_below_ground' => 'integer',
        'gross_area_sqm' => 'decimal:4',
        'net_lettable_area_sqm' => 'decimal:4',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class, 'building_id');
    }

    public function rentalUnits(): HasMany
    {
        return $this->hasMany(RentalUnit::class, 'building_id');
    }
}

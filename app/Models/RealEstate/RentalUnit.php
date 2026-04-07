<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalUnit extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_rental_units';

    protected $fillable = [
        'organization_id',
        'building_id',
        'floor_id',
        'code',
        'name',
        'unit_type',
        'area_sqm',
        'status',
        'usage_type',
        'rooms',
        'bathrooms',
        'has_parking',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'area_sqm' => 'decimal:4',
        'rooms' => 'integer',
        'bathrooms' => 'integer',
        'has_parking' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(LeaseContract::class, 'rental_unit_id');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(LeaseContract::class, 'rental_unit_id')
            ->where('status', 'active');
    }

    public function isVacant(): bool
    {
        return $this->status === 'vacant';
    }
}

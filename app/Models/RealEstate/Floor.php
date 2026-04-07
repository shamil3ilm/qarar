<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    protected $table = 're_floors';

    protected $fillable = [
        'building_id',
        'floor_number',
        'floor_label',
        'total_area_sqm',
        'lettable_area_sqm',
    ];

    protected $casts = [
        'floor_number' => 'integer',
        'total_area_sqm' => 'decimal:4',
        'lettable_area_sqm' => 'decimal:4',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function rentalUnits(): HasMany
    {
        return $this->hasMany(RentalUnit::class, 'floor_id');
    }
}

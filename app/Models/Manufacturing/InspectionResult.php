<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_lot_id',
        'quality_plan_characteristic_id',
        'characteristic_name',
        'measured_value',
        'text_result',
        'is_conforming',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'measured_value' => 'decimal:4',
        'is_conforming' => 'boolean',
    ];

    // Relationships

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InspectionLot::class, 'inspection_lot_id');
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(QualityPlanCharacteristic::class, 'quality_plan_characteristic_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Helper Methods

    public function isConforming(): bool
    {
        return $this->is_conforming === true;
    }

    public function isNonConforming(): bool
    {
        return $this->is_conforming === false;
    }

    public function isPending(): bool
    {
        return $this->is_conforming === null;
    }
}

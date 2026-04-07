<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityPlanCharacteristic extends Model
{
    use HasFactory;

    protected $fillable = [
        'quality_plan_id',
        'name',
        'description',
        'inspection_method',
        'measurement_unit',
        'lower_limit',
        'upper_limit',
        'target_value',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'lower_limit' => 'decimal:4',
        'upper_limit' => 'decimal:4',
        'target_value' => 'decimal:4',
        'is_mandatory' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class, 'quality_plan_id');
    }

    // Helper Methods

    /**
     * Check whether a measured numeric value falls within the defined limits.
     * Returns true when no limits are defined (unconditional pass).
     */
    public function isWithinLimits(float $value): bool
    {
        $hasLower = $this->lower_limit !== null;
        $hasUpper = $this->upper_limit !== null;

        if (!$hasLower && !$hasUpper) {
            return true;
        }

        if ($hasLower && $value < (float) $this->lower_limit) {
            return false;
        }

        if ($hasUpper && $value > (float) $this->upper_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check whether this characteristic has numeric limits configured.
     */
    public function hasNumericLimits(): bool
    {
        return $this->lower_limit !== null || $this->upper_limit !== null;
    }
}

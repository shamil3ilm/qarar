<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalibrationPlan extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'calibration_equipment_id',
        'plan_code',
        'calibration_interval_days',
        'tolerance_low',
        'tolerance_high',
        'measurement_unit',
        'calibration_procedure',
        'external_lab',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'calibration_interval_days' => 'integer',
            'tolerance_low'             => 'decimal:4',
            'tolerance_high'            => 'decimal:4',
            'is_active'                 => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(CalibrationEquipment::class, 'calibration_equipment_id');
    }

    public function calibrationOrders(): HasMany
    {
        return $this->hasMany(CalibrationOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateNextDueDate(\DateTimeInterface $from): \DateTimeImmutable
    {
        $base = \DateTimeImmutable::createFromInterface($from);

        return $base->modify("+{$this->calibration_interval_days} days");
    }
}

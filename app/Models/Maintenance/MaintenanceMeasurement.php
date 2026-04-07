<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceMeasurement extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'measurement_point',
        'measurement_value',
        'unit_of_measure',
        'measured_at',
        'recorded_by',
        'threshold_breached',
        'triggered_rule_id',
    ];

    protected function casts(): array
    {
        return [
            'measurement_value' => 'decimal:4',
            'measured_at'       => 'datetime',
            'threshold_breached' => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function triggeredRule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceConditionRule::class, 'triggered_rule_id');
    }

    public function scopeBreached($query)
    {
        return $query->where('threshold_breached', true);
    }

    public function scopeForEquipment($query, int $equipmentId)
    {
        return $query->where('equipment_id', $equipmentId);
    }

    public function scopeForMeasurementPoint($query, string $point)
    {
        return $query->where('measurement_point', $point);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceConditionRule extends Model
{
    use BelongsToOrganization, HasFactory;

    public const OPERATOR_GREATER_THAN = 'greater_than';
    public const OPERATOR_LESS_THAN    = 'less_than';
    public const OPERATOR_EQUALS       = 'equals';
    public const OPERATOR_BETWEEN      = 'between';

    public const ACTION_CREATE_ORDER = 'create_order';
    public const ACTION_NOTIFY       = 'notify';
    public const ACTION_BOTH         = 'both';

    public const TYPE_INSPECTION  = 'inspection';
    public const TYPE_REPAIR      = 'repair';
    public const TYPE_OVERHAUL    = 'overhaul';
    public const TYPE_REPLACEMENT = 'replacement';

    protected $fillable = [
        'organization_id',
        'rule_name',
        'equipment_id',
        'measurement_point',
        'condition_operator',
        'threshold_value',
        'threshold_value_to',
        'unit_of_measure',
        'trigger_action',
        'maintenance_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value'    => 'decimal:4',
            'threshold_value_to' => 'decimal:4',
            'is_active'          => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MaintenanceMeasurement::class, 'triggered_rule_id');
    }

    /**
     * Check whether a given measurement value breaches this rule.
     */
    public function isBreached(float $value): bool
    {
        return match ($this->condition_operator) {
            self::OPERATOR_GREATER_THAN => $value > (float) $this->threshold_value,
            self::OPERATOR_LESS_THAN    => $value < (float) $this->threshold_value,
            self::OPERATOR_EQUALS       => $value === (float) $this->threshold_value,
            self::OPERATOR_BETWEEN      => $value >= (float) $this->threshold_value
                && $value <= (float) ($this->threshold_value_to ?? $this->threshold_value),
            default => false,
        };
    }

    public function shouldCreateOrder(): bool
    {
        return in_array($this->trigger_action, [self::ACTION_CREATE_ORDER, self::ACTION_BOTH], true);
    }

    public function shouldNotify(): bool
    {
        return in_array($this->trigger_action, [self::ACTION_NOTIFY, self::ACTION_BOTH], true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

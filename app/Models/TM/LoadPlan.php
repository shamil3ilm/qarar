<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoadPlan extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_load_plans';

    protected $fillable = [
        'organization_id',
        'plan_number',
        'status',
        'carrier_id',
        'carrier_service_id',
        'vehicle_type',
        'vehicle_plate',
        'driver_name',
        'driver_contact',
        'max_weight',
        'max_volume',
        'current_weight',
        'current_volume',
        'utilization_weight_pct',
        'utilization_volume_pct',
        'planned_departure',
        'actual_departure',
        'origin_location',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'max_weight' => 'decimal:3',
        'max_volume' => 'decimal:4',
        'current_weight' => 'decimal:3',
        'current_volume' => 'decimal:4',
        'utilization_weight_pct' => 'decimal:2',
        'utilization_volume_pct' => 'decimal:2',
        'planned_departure' => 'datetime',
        'actual_departure' => 'datetime',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function carrierService(): BelongsTo
    {
        return $this->belongsTo(CarrierService::class, 'carrier_service_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoadPlanItem::class, 'load_plan_id');
    }

    public function transportationOrders(): HasMany
    {
        return $this->hasMany(TransportationOrder::class, 'load_plan_id');
    }

    public function isCapacityAvailable(float $weight, float $volume): bool
    {
        if ($this->max_weight !== null && bccomp(
            bcadd((string) $this->current_weight, (string) $weight, 3),
            (string) $this->max_weight,
            3
        ) > 0) {
            return false;
        }

        if ($this->max_volume !== null && bccomp(
            bcadd((string) $this->current_volume, (string) $volume, 4),
            (string) $this->max_volume,
            4
        ) > 0) {
            return false;
        }

        return true;
    }
}

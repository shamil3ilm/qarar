<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_CAR        = 'car';
    public const TYPE_VAN        = 'van';
    public const TYPE_TRUCK      = 'truck';
    public const TYPE_MOTORCYCLE = 'motorcycle';
    public const TYPE_BUS        = 'bus';
    public const TYPE_OTHER      = 'other';

    public const FUEL_PETROL   = 'petrol';
    public const FUEL_DIESEL   = 'diesel';
    public const FUEL_ELECTRIC = 'electric';
    public const FUEL_HYBRID   = 'hybrid';
    public const FUEL_CNG      = 'cng';

    protected $fillable = [
        'organization_id',
        'fleet_number',
        'license_plate',
        'make',
        'model',
        'year',
        'vin',
        'vehicle_type',
        'fuel_type',
        'color',
        'department_id',
        'current_mileage_km',
        'last_service_km',
        'next_service_km',
        'insurance_expiry',
        'registration_expiry',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year'                => 'integer',
            'current_mileage_km'  => 'integer',
            'last_service_km'     => 'integer',
            'next_service_km'     => 'integer',
            'insurance_expiry'    => 'date',
            'registration_expiry' => 'date',
            'is_active'           => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function currentAssignment(): ?VehicleAssignment
    {
        return $this->assignments()->where('is_current', true)->first();
    }

    public function mileageLogs(): HasMany
    {
        return $this->hasMany(MileageLog::class)->orderByDesc('log_date');
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class)->orderByDesc('log_date');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class)->orderByDesc('service_date');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function requiresService(): bool
    {
        if ($this->next_service_km !== null && $this->current_mileage_km >= $this->next_service_km) {
            return true;
        }

        return false;
    }

    public function isInsuranceExpiringSoon(int $withinDays = 30): bool
    {
        if ($this->insurance_expiry === null) {
            return false;
        }

        return $this->insurance_expiry->diffInDays(now(), false) >= -$withinDays;
    }
}

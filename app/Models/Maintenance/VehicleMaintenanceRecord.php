<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleMaintenanceRecord extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_SCHEDULED   = 'scheduled';
    public const TYPE_UNSCHEDULED = 'unscheduled';
    public const TYPE_REPAIR      = 'repair';
    public const TYPE_INSPECTION  = 'inspection';

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'maintenance_type',
        'service_date',
        'odometer_reading',
        'description',
        'cost',
        'currency_code',
        'service_provider',
        'next_service_date',
        'next_service_km',
        'maintenance_order_id',
    ];

    protected function casts(): array
    {
        return [
            'service_date'     => 'date',
            'next_service_date' => 'date',
            'odometer_reading' => 'integer',
            'next_service_km'  => 'integer',
            'cost'             => 'decimal:4',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

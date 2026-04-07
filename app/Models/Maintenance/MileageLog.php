<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MileageLog extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'log_date',
        'odometer_start',
        'odometer_end',
        'distance_km',
        'trip_purpose',
        'driver_id',
        'route',
    ];

    protected function casts(): array
    {
        return [
            'log_date'      => 'date',
            'odometer_start' => 'integer',
            'odometer_end'  => 'integer',
            'distance_km'   => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }
}

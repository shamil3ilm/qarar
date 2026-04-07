<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAssignment extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'assigned_from',
        'assigned_to',
        'purpose',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'datetime',
            'assigned_to'   => 'datetime',
            'is_current'    => 'boolean',
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

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}

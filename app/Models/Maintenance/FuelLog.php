<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'log_date',
        'odometer_reading',
        'fuel_quantity_liters',
        'fuel_cost',
        'currency_code',
        'fuel_type',
        'station',
        'filled_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date'             => 'date',
            'odometer_reading'     => 'integer',
            'fuel_quantity_liters' => 'decimal:3',
            'fuel_cost'            => 'decimal:4',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'filled_by');
    }

    public function getCostPerLiter(): float
    {
        $liters = (float) $this->fuel_quantity_liters;

        if ($liters === 0.0) {
            return 0.0;
        }

        return round((float) $this->fuel_cost / $liters, 4);
    }
}

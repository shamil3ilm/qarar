<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceKpi extends Model
{
    use HasUuid;

    protected $table = 'maintenance_kpis';

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'period_year',
        'period_month',
        'mtbf_hours',
        'mttr_hours',
        'availability_pct',
        'oee_pct',
        'breakdown_count',
        'total_downtime_hours',
        'planned_maintenance_hours',
        'unplanned_maintenance_hours',
        'maintenance_cost',
    ];

    protected $casts = [
        'mtbf_hours'                  => 'decimal:2',
        'mttr_hours'                  => 'decimal:2',
        'availability_pct'            => 'decimal:2',
        'oee_pct'                     => 'decimal:2',
        'total_downtime_hours'        => 'decimal:2',
        'planned_maintenance_hours'   => 'decimal:2',
        'unplanned_maintenance_hours' => 'decimal:2',
        'maintenance_cost'            => 'decimal:4',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}

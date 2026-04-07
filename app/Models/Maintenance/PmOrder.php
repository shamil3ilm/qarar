<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmOrder extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'order_number', 'maintenance_plan_id',
        'floc_id', 'order_type', 'description', 'status', 'priority',
        'planned_start', 'planned_end', 'actual_start', 'actual_end',
        'counter_reading_at_trigger', 'assigned_to',
    ];

    protected $casts = [
        'planned_start'               => 'date',
        'planned_end'                 => 'date',
        'actual_start'                => 'date',
        'actual_end'                  => 'date',
        'counter_reading_at_trigger'  => 'decimal:3',
    ];

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(PmMaintenancePlan::class, 'maintenance_plan_id');
    }

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'floc_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

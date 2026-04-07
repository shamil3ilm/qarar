<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmMaintenancePlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'plan_number', 'plan_type', 'floc_id',
        'counter_id', 'task_list_id', 'counter_interval', 'threshold_warning',
        'last_maintenance_reading', 'next_due_reading', 'active',
    ];

    protected $casts = [
        'counter_interval'          => 'decimal:3',
        'threshold_warning'         => 'decimal:3',
        'last_maintenance_reading'  => 'decimal:3',
        'next_due_reading'          => 'decimal:3',
        'active'                    => 'boolean',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'floc_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(PmCounter::class, 'counter_id');
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(PmTaskList::class, 'task_list_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PmOrder::class, 'maintenance_plan_id');
    }
}

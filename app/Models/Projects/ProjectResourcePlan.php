<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectResourcePlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'wbs_element', 'resource_type',
        'resource_id', 'resource_description', 'planned_quantity', 'uom',
        'planned_start', 'planned_end', 'cost_rate', 'planned_cost',
        'actual_quantity', 'actual_cost',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:2',
        'actual_quantity'  => 'decimal:2',
        'cost_rate'        => 'decimal:4',
        'planned_cost'     => 'decimal:4',
        'actual_cost'      => 'decimal:4',
        'planned_start'    => 'date',
        'planned_end'      => 'date',
    ];
}

<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRevenuePlanLine extends Model
{
    protected $fillable = [
        'project_revenue_plan_id', 'period_month',
        'planned_revenue', 'planned_cost', 'actual_revenue', 'actual_cost',
    ];

    protected $casts = [
        'planned_revenue' => 'decimal:4',
        'planned_cost'    => 'decimal:4',
        'actual_revenue'  => 'decimal:4',
        'actual_cost'     => 'decimal:4',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProjectRevenuePlan::class, 'project_revenue_plan_id');
    }
}

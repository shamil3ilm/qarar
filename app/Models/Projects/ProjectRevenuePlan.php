<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectRevenuePlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'fiscal_year', 'version',
        'status', 'total_planned_revenue', 'total_planned_cost', 'currency', 'approved_by',
    ];

    protected $casts = [
        'total_planned_revenue' => 'decimal:4',
        'total_planned_cost'    => 'decimal:4',
    ];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProjectRevenuePlanLine::class)->orderBy('period_month');
    }
}

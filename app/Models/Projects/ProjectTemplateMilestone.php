<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTemplateMilestone extends Model
{
    protected $fillable = [
        'project_template_id', 'milestone_name', 'offset_days',
        'milestone_type', 'billing_percentage',
    ];

    protected $casts = ['billing_percentage' => 'decimal:2'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }
}

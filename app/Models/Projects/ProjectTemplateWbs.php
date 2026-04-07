<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplateWbs extends Model
{
    use HasUuid;

    protected $table = 'project_template_wbs';

    protected $fillable = [
        'uuid', 'project_template_id', 'wbs_code', 'description',
        'parent_id', 'level', 'duration_days', 'planned_cost', 'responsible_dept_id',
    ];

    protected $casts = ['planned_cost' => 'decimal:4'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('wbs_code');
    }
}

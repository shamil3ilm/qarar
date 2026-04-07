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

class ProjectTemplate extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'template_name', 'description',
        'project_type', 'industry', 'active', 'created_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wbsElements(): HasMany
    {
        return $this->hasMany(ProjectTemplateWbs::class)->orderBy('level')->orderBy('wbs_code');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectTemplateMilestone::class)->orderBy('offset_days');
    }
}

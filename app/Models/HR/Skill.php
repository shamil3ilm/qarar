<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Skill extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'skill_category_id',
        'name',
        'description',
        'proficiency_scale',
    ];

    protected function casts(): array
    {
        return [
            'proficiency_scale' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'skill_category_id');
    }

    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(
            CompetencyFramework::class,
            'competency_framework_skills',
            'skill_id',
            'competency_framework_id'
        )->withPivot(['required_level', 'weight'])->withTimestamps();
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeSkillProfile::class);
    }
}

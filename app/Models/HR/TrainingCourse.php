<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingCourse extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'training_courses';

    // Category constants
    public const CATEGORY_TECHNICAL   = 'technical';
    public const CATEGORY_SOFT_SKILLS = 'soft_skills';
    public const CATEGORY_COMPLIANCE  = 'compliance';
    public const CATEGORY_SAFETY      = 'safety';
    public const CATEGORY_LEADERSHIP  = 'leadership';
    public const CATEGORY_ONBOARDING  = 'onboarding';
    public const CATEGORY_OTHER       = 'other';

    public const CATEGORIES = [
        self::CATEGORY_TECHNICAL,
        self::CATEGORY_SOFT_SKILLS,
        self::CATEGORY_COMPLIANCE,
        self::CATEGORY_SAFETY,
        self::CATEGORY_LEADERSHIP,
        self::CATEGORY_ONBOARDING,
        self::CATEGORY_OTHER,
    ];

    // Delivery type constants
    public const DELIVERY_IN_PERSON  = 'in_person';
    public const DELIVERY_ONLINE     = 'online';
    public const DELIVERY_BLENDED    = 'blended';
    public const DELIVERY_SELF_PACED = 'self_paced';

    public const DELIVERY_TYPES = [
        self::DELIVERY_IN_PERSON,
        self::DELIVERY_ONLINE,
        self::DELIVERY_BLENDED,
        self::DELIVERY_SELF_PACED,
    ];

    protected $fillable = [
        'organization_id',
        'provider_id',
        'code',
        'name',
        'description',
        'category',
        'delivery_type',
        'duration_hours',
        'max_participants',
        'is_mandatory',
        'validity_months',
        'cost_per_participant',
        'currency_code',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours'       => 'float',
            'max_participants'     => 'integer',
            'is_mandatory'         => 'boolean',
            'validity_months'      => 'integer',
            'cost_per_participant' => 'float',
            'is_active'            => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'provider_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'course_id');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(TrainingCertification::class, 'course_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMandatory(): bool
    {
        return (bool) $this->is_mandatory;
    }

    public function requiresRecertification(): bool
    {
        return $this->validity_months !== null && $this->validity_months > 0;
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMandatory(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_mandatory', true);
    }
}

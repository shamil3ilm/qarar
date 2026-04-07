<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeWageType extends Model
{
    use BelongsToOrganization;

    public const CATEGORY_OVERTIME           = 'overtime';
    public const CATEGORY_NIGHT_DIFFERENTIAL = 'night_differential';
    public const CATEGORY_WEEKEND            = 'weekend';
    public const CATEGORY_HOLIDAY            = 'holiday';
    public const CATEGORY_ABSENCE_DEDUCTION  = 'absence_deduction';
    public const CATEGORY_OTHER              = 'other';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'wage_category',
        'rate_multiplier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_multiplier' => 'decimal:4',
            'is_active'       => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function evaluationResults(): HasMany
    {
        return $this->hasMany(TimeEvaluationResult::class, 'wage_type_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('wage_category', $category);
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->is_active;
    }
}

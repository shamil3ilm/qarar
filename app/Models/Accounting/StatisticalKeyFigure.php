<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatisticalKeyFigure extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_TOTAL = 'total';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'unit_of_measure',
        'skf_type',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function values(): HasMany
    {
        return $this->hasMany(StatisticalKeyFigureValue::class, 'statistical_key_figure_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

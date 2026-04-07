<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostSplittingRule extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const BASIS_ACTIVITY_QUANTITY    = 'activity_quantity';
    public const BASIS_CAPACITY_UTILIZATION = 'capacity_utilization';
    public const BASIS_MANUAL              = 'manual';

    protected $fillable = [
        'organization_id',
        'cost_center_id',
        'cost_element_id',
        'fixed_percentage',
        'variable_percentage',
        'splitting_basis',
        'is_active',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'fixed_percentage'    => 'decimal:2',
            'variable_percentage' => 'decimal:2',
            'is_active'           => 'boolean',
            'valid_from'          => 'date',
            'valid_to'            => 'date',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CostSplittingResult::class, 'cost_splitting_rule_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

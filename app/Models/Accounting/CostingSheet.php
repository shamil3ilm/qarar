<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostingSheet extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'cost_component_structure_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                    => 'boolean',
            'cost_component_structure_id'  => 'integer',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function rows(): HasMany
    {
        return $this->hasMany(CostingSheetRow::class, 'costing_sheet_id')->orderBy('sort_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CostingSheetRun::class, 'costing_sheet_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

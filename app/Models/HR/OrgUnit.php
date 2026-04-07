<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Accounting\CostCenter;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgUnit extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_COMPANY         = 'company';
    public const TYPE_DIVISION        = 'division';
    public const TYPE_DEPARTMENT      = 'department';
    public const TYPE_TEAM            = 'team';
    public const TYPE_COST_CENTER_UNIT = 'cost_center_unit';

    protected $fillable = [
        'organization_id',
        'org_unit_code',
        'name',
        'short_name',
        'parent_id',
        'org_unit_type',
        'cost_center_id',
        'manager_position_id',
        'head_count_plan',
        'valid_from',
        'valid_to',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'      => 'date',
            'valid_to'        => 'date',
            'is_active'       => 'boolean',
            'head_count_plan' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function managerPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'manager_position_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now()->toDateString());
            });
    }

    /**
     * Walk up the parent chain and return an ordered collection from root to this unit.
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $current   = $this->parent;

        while ($current !== null) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Recursively collect all descendant OrgUnits.
     */
    public function getDescendants(): Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }
}

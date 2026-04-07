<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use HasAuditTrail;
    use SoftDeletes;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'description',
        'manager_id',
        'department_id',
        'status',
        'valid_from',
        'valid_to',
        'gl_account_id',
        'is_statistical',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'      => 'date',
            'valid_to'        => 'date',
            'is_statistical'  => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    /**
     * Polymorphic assignments where this cost center is the target.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CostCenterAssignment::class, 'cost_center_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Build the breadcrumb path using the parent chain.
     * Returns something like: "CORP / MFG / DEPT-01"
     */
    public function getFullPath(): string
    {
        $parts   = [$this->code];
        $current = $this->parent;

        while ($current !== null) {
            array_unshift($parts, $current->code);
            $current = $current->parent;
        }

        return implode(' / ', $parts);
    }
}

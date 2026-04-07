<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenterBudget extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE   = 'active';

    protected $fillable = [
        'organization_id',
        'cost_center_id',
        'fiscal_year',
        'budget_version',
        'total_budget',
        'currency',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'total_budget' => 'float',
            'approved_at'  => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostCenterBudgetLine::class, 'cost_center_budget_id');
    }

    public function supplements(): HasMany
    {
        return $this->hasMany(CostCenterBudgetSupplement::class, 'cost_center_budget_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBudgetVersion extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_FROZEN   = 'frozen';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'organization_id',
        'project_id',
        'version_code',
        'version_name',
        'fiscal_year',
        'status',
        'is_current',
        'total_budget',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'fiscal_year'   => 'integer',
        'is_current'    => 'boolean',
        'total_budget'  => 'decimal:4',
        'approved_at'   => 'datetime',
        'status'        => 'string',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ProjectBudgetLineItem::class, 'project_budget_version_id');
    }

    public function supplements(): HasMany
    {
        return $this->hasMany(ProjectBudgetSupplement::class, 'project_budget_version_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Activate this version. If marked as current, demotes all other versions
     * for the same project first, then promotes this one.
     */
    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;

        if ($this->is_current) {
            static::withoutGlobalScope('organization')
                ->where('project_id', $this->project_id)
                ->where('id', '!=', $this->id)
                ->update(['is_current' => false]);
        }

        $this->save();
    }

    /**
     * Recalculate total_budget as sum of line item budgeted amounts
     * plus all approved supplement amounts.
     */
    public function recalculateTotalBudget(): void
    {
        $lineItemSum   = (float) $this->lineItems()->sum('budgeted_amount');
        $supplementSum = (float) $this->supplements()->where('status', 'approved')->sum('amount');

        $this->total_budget = $lineItemSum + $supplementSum;
        $this->save();
    }
}

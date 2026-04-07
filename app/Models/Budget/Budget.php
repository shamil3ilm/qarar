<?php

declare(strict_types=1);

namespace App\Models\Budget;

use App\Models\Accounting\FiscalYear;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'budgets';

    protected $guarded = ['id'];

    // Budget type constants
    public const TYPE_ANNUAL     = 'annual';
    public const TYPE_QUARTERLY  = 'quarterly';
    public const TYPE_PROJECT    = 'project';
    public const TYPE_DEPARTMENT = 'department';

    // Status constants
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'period_start'    => 'date',
            'period_end'      => 'date',
            'total_amount'    => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'approved_at'     => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRevision::class, 'budget_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('budget_type', $type);
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->where('period_start', '<=', $to)
            ->where('period_end', '>=', $from);
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getTotalBudgeted(): float
    {
        return (float) $this->lines()->sum('total_amount');
    }

    public function getTotalCommitted(): float
    {
        return (float) $this->lines()->sum('committed_amount');
    }

    public function getTotalActual(): float
    {
        return (float) $this->lines()->sum('actual_amount');
    }

    public function getRemainingBudget(): float
    {
        return $this->getTotalBudgeted() - $this->getTotalCommitted() - $this->getTotalActual();
    }

    public function getUtilizationPercent(): float
    {
        $budgeted = $this->getTotalBudgeted();

        if ($budgeted <= 0.0) {
            return 0.0;
        }

        return round(($this->getTotalActual() / $budgeted) * 100, 2);
    }

    // @deprecated Use getTotalCommitted() instead
    public function getTotalCommittedAmount(): float
    {
        return $this->getTotalCommitted();
    }

    public function getVarianceAmount(): float
    {
        return (float) $this->approved_amount - $this->getTotalActual();
    }
}

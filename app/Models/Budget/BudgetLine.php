<?php

declare(strict_types=1);

namespace App\Models\Budget;

use App\Models\Accounting\Account;
use App\Models\HR\Department;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetLine extends Model
{
    use HasFactory;

    protected $table = 'budget_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'q1_amount'        => 'decimal:2',
            'q2_amount'        => 'decimal:2',
            'q3_amount'        => 'decimal:2',
            'q4_amount'        => 'decimal:2',
            'total_amount'     => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'actual_amount'    => 'decimal:2',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function costCenter(): BelongsTo
    {
        // Uses the cost_centers table via the Accounting CostCenter model if present,
        // otherwise falls back to a generic relationship by table name.
        return $this->belongsTo(\App\Models\Accounting\CostCenter::class, 'cost_center_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class, 'budget_line_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function getVarianceAmount(): float
    {
        return (float) $this->total_amount - (float) $this->actual_amount;
    }

    /** Alias used by BudgetService reporting. */
    public function getVariance(): float
    {
        return $this->getVarianceAmount();
    }

    public function getVariancePercent(): float
    {
        $total = (float) $this->total_amount;

        if ($total <= 0.0) {
            return 0.0;
        }

        return round(($this->getVariance() / $total) * 100, 2);
    }

    public function isOverBudget(): bool
    {
        return (float) $this->actual_amount > (float) $this->total_amount;
    }

    public function getAvailableAmount(): float
    {
        return (float) $this->total_amount - (float) $this->committed_amount - (float) $this->actual_amount;
    }
}

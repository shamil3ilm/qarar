<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExpenseBudget extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'category_id',
        'department_id',
        'year',
        'month',
        'budget_amount',
        'spent_amount',
        'committed_amount',
        'alert_at_80',
        'alert_at_100',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'budget_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'alert_at_80' => 'boolean',
            'alert_at_100' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Get the total utilized amount (spent + committed).
     */
    public function getTotalUtilized(): float
    {
        return (float) ($this->spent_amount + $this->committed_amount);
    }

    /**
     * Get utilization percentage.
     */
    public function getUtilizationPercentage(): float
    {
        if ($this->budget_amount <= 0) {
            return 0;
        }

        return round(($this->getTotalUtilized() / (float) $this->budget_amount) * 100, 2);
    }

    /**
     * Get remaining budget.
     */
    public function getRemainingBudget(): float
    {
        return max(0, (float) $this->budget_amount - $this->getTotalUtilized());
    }

    /**
     * Check if budget is exceeded.
     */
    public function isExceeded(): bool
    {
        return $this->getTotalUtilized() > (float) $this->budget_amount;
    }

    /**
     * Check if budget is at 80% threshold.
     */
    public function isAt80Percent(): bool
    {
        return $this->getUtilizationPercentage() >= 80;
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth($query, ?int $month)
    {
        if ($month === null) {
            return $query->whereNull('month');
        }
        return $query->where('month', $month);
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeForDepartment($query, ?int $departmentId)
    {
        if ($departmentId === null) {
            return $query->whereNull('department_id');
        }
        return $query->where('department_id', $departmentId);
    }
}

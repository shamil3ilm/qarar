<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExpenseReport extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'report_number',
        'title',
        'description',
        'period_start',
        'period_end',
        'total_amount',
        'approved_amount',
        'reimbursed_amount',
        'status',
        'approved_by',
        'approved_at',
        'paid_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'reimbursed_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExpenseReport $report) {
            if (!$report->report_number) {
                $report->report_number = static::generateReportNumber($report->organization_id);
            }
        });
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(ExpenseReportItem::class, 'report_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Recalculate total amount from items.
     */
    public function recalculateTotals(): void
    {
        $this->total_amount = $this->reportItems()
            ->join('expenses', 'expenses.id', '=', 'expense_report_items.expense_id')
            ->sum('expenses.total_amount');
        $this->saveQuietly();
    }

    public static function generateReportNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $prefix = "ER-{$year}-";

        $lastNumber = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('report_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(report_number, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
            ->value('report_number');

        if ($lastNumber) {
            $sequence = (int) substr($lastNumber, strlen($prefix)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}

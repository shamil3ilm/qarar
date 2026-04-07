<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollCorrection extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_SALARY_CHANGE = 'salary_change';
    public const TYPE_COMPONENT_ADJUSTMENT = 'component_adjustment';
    public const TYPE_TAX_CORRECTION = 'tax_correction';
    public const TYPE_DEDUCTION_ADJUSTMENT = 'deduction_adjustment';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'original_payroll_period_id',
        'correction_payroll_period_id',
        'correction_type',
        'status',
        'original_amount',
        'corrected_amount',
        'difference_amount',
        'reason',
        'approved_by',
        'approved_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount'   => 'decimal:4',
            'corrected_amount'  => 'decimal:4',
            'difference_amount' => 'decimal:4',
            'approved_at'       => 'datetime',
            'posted_at'         => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function originalPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'original_payroll_period_id');
    }

    public function correctionPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'correction_payroll_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('original_payroll_period_id', $periodId);
    }

    // Helpers

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LeaveBalance extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'leave_balances';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'leave_type_id',
        'leave_tier_id',
        'year',
        'opening_balance',
        'entitled_days',
        'accrued_days',
        'adjustment_days',
        'used_days',
        'pending_days',
        'carried_forward',
        'encashed_days',
        'lapsed_days',
        'available_balance',
        'last_accrual_date',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opening_balance' => 'decimal:2',
            'entitled_days' => 'decimal:2',
            'accrued_days' => 'decimal:2',
            'adjustment_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'encashed_days' => 'decimal:2',
            'lapsed_days' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'last_accrual_date' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveTier(): BelongsTo
    {
        return $this->belongsTo(LeaveTier::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveAdjustment::class);
    }

    public function recalculateAvailableBalance(): void
    {
        $this->available_balance = (float) bcadd(
            bcadd(
                bcadd((string) $this->opening_balance, (string) $this->entitled_days, 2),
                bcadd((string) $this->accrued_days, (string) $this->adjustment_days, 2),
                2
            ),
            bcadd(
                (string) $this->carried_forward,
                bcsub(
                    '0',
                    bcadd(
                        bcadd((string) $this->used_days, (string) $this->pending_days, 2),
                        bcadd((string) $this->encashed_days, (string) $this->lapsed_days, 2),
                        2
                    ),
                    2
                ),
                2
            ),
            2
        );
    }

    public function getAvailableBalance(): float
    {
        return max(0, (float) $this->available_balance);
    }

    public function hasBalance(float $days): bool
    {
        return $this->getAvailableBalance() >= $days;
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForLeaveType($query, int $leaveTypeId)
    {
        return $query->where('leave_type_id', $leaveTypeId);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('available_balance', '>', 0);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeExit extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_RESIGNATION = 'resignation';
    public const TYPE_TERMINATION = 'termination';
    public const TYPE_RETIREMENT = 'retirement';
    public const TYPE_CONTRACT_END = 'contract_end';
    public const TYPE_DEATH = 'death';

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_NOTICE_PERIOD = 'notice_period';
    public const STATUS_CLEARANCE_IN_PROGRESS = 'clearance_in_progress';
    public const STATUS_CLEARANCE_COMPLETE = 'clearance_complete';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'exit_type',
        'resignation_date',
        'last_working_date',
        'notice_period_days',
        'notice_period_waived',
        'exit_reason',
        'status',
        'final_settlement_amount',
        'settlement_date',
        'eosb_amount',
        'leave_encashment_amount',
        'initiated_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date'         => 'date',
            'last_working_date'        => 'date',
            'settlement_date'          => 'date',
            'approved_at'              => 'datetime',
            'notice_period_waived'     => 'boolean',
            'final_settlement_amount'  => 'decimal:4',
            'eosb_amount'              => 'decimal:4',
            'leave_encashment_amount'  => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function clearanceItems(): HasMany
    {
        return $this->hasMany(ExitClearanceItem::class)->orderBy('sort_order');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function scopeInNoticePeriod(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NOTICE_PERIOD);
    }

    // Helpers

    public function getNoticePeriodEndDate(): ?Carbon
    {
        if ($this->resignation_date === null) {
            return null;
        }

        return $this->resignation_date->copy()->addDays($this->notice_period_days);
    }

    public function isNoticePeriodComplete(): bool
    {
        $endDate = $this->getNoticePeriodEndDate();

        if ($endDate === null || $this->notice_period_waived) {
            return true;
        }

        return Carbon::today()->gte($endDate);
    }

    public function isPendingClearance(): bool
    {
        return in_array($this->status, [
            self::STATUS_NOTICE_PERIOD,
            self::STATUS_CLEARANCE_IN_PROGRESS,
        ], true);
    }
}

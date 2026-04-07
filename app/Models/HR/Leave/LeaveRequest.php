<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use App\Models\User;
use Database\Factories\HR\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected static function newFactory(): LeaveRequestFactory
    {
        return LeaveRequestFactory::new();
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_TAKEN = 'taken';

    public const DAY_TYPE_FULL = 'full';
    public const DAY_TYPE_FIRST_HALF = 'first_half';
    public const DAY_TYPE_SECOND_HALF = 'second_half';

    protected $table = 'leave_requests';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'leave_type_id',
        'leave_balance_id',
        'request_number',
        'start_date',
        'end_date',
        'total_days',
        'day_type',
        'reason',
        'contact_during_leave',
        'delegated_to',
        'status',
        'is_emergency',
        'has_attachments',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:2',
            'is_emergency' => 'boolean',
            'has_attachments' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegated_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class);
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(LeaveCalendar::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate]);
        });
    }
}

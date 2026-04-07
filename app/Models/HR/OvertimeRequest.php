<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID     = 'paid';

    public const DAY_TYPE_WEEKDAY = 'weekday';
    public const DAY_TYPE_WEEKEND = 'weekend';
    public const DAY_TYPE_HOLIDAY = 'holiday';

    protected $fillable = [
        'employee_id',
        'policy_id',
        'ot_date',
        'ot_start',
        'ot_end',
        'ot_hours',
        'reason',
        'day_type',
        'ot_rate',
        'ot_amount',
        'status',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ot_date'   => 'date',
            'ot_hours'  => 'decimal:2',
            'ot_rate'   => 'decimal:2',
            'ot_amount' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'policy_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeInMonth($query, int $year, int $month)
    {
        return $query->whereYear('ot_date', $year)->whereMonth('ot_date', $month);
    }
}

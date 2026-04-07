<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeSheet extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_DRAFT                 = 'draft';
    public const STATUS_SUBMITTED             = 'submitted';
    public const STATUS_APPROVED              = 'approved';
    public const STATUS_REJECTED              = 'rejected';
    public const STATUS_TRANSFERRED_TO_PAYROLL = 'transferred_to_payroll';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'period_start',
        'period_end',
        'status',
        'approved_by',
        'approved_at',
        'total_regular_hours',
        'total_overtime_hours',
        'total_absence_hours',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start'         => 'date',
            'period_end'           => 'date',
            'approved_at'          => 'datetime',
            'total_regular_hours'  => 'decimal:2',
            'total_overtime_hours' => 'decimal:2',
            'total_absence_hours'  => 'decimal:2',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimeSheetEntry::class, 'time_sheet_id');
    }

    public function evaluationResults(): HasMany
    {
        return $this->hasMany(TimeEvaluationResult::class, 'time_sheet_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isTransferred(): bool
    {
        return $this->status === self::STATUS_TRANSFERRED_TO_PAYROLL;
    }

    public function getTotalHours(): float
    {
        return (float) ($this->total_regular_hours + $this->total_overtime_hours);
    }
}

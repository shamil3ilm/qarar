<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Database\Factories\HR\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected static function newFactory(): LeaveTypeFactory
    {
        return LeaveTypeFactory::new();
    }

    public const ACCRUAL_YEARLY = 'yearly';
    public const ACCRUAL_MONTHLY = 'monthly';
    public const ACCRUAL_WEEKLY = 'weekly';
    public const ACCRUAL_NONE = 'none';

    protected $fillable = [
        'organization_id',
        'leave_policy_id',
        'name',
        'code',
        'description',
        'color',
        'icon',
        'is_paid',
        'is_encashable',
        'is_carryforward_allowed',
        'max_carryforward_days',
        'requires_attachment',
        'requires_reason',
        'gender_restriction',
        'employment_type_restriction',
        'min_service_months',
        'max_consecutive_days',
        'min_days_per_request',
        'max_days_per_request',
        'allowed_days_of_week',
        'blackout_dates',
        'accrual_type',
        'accrual_day',
        'count_holidays',
        'count_weekends',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_encashable' => 'boolean',
            'is_carryforward_allowed' => 'boolean',
            'max_carryforward_days' => 'integer',
            'requires_attachment' => 'boolean',
            'requires_reason' => 'boolean',
            'min_service_months' => 'integer',
            'max_consecutive_days' => 'integer',
            'min_days_per_request' => 'integer',
            'max_days_per_request' => 'integer',
            'allowed_days_of_week' => 'array',
            'blackout_dates' => 'array',
            'accrual_day' => 'integer',
            'count_holidays' => 'boolean',
            'count_weekends' => 'boolean',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function leaveTiers(): HasMany
    {
        return $this->hasMany(LeaveTier::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isApplicableToEmployee(Employee $employee): bool
    {
        if ($this->gender_restriction && $employee->gender !== $this->gender_restriction) {
            return false;
        }

        if ($this->employment_type_restriction && $employee->employment_type !== $this->employment_type_restriction) {
            return false;
        }

        if ($this->min_service_months > 0 && $employee->getTenureInMonths() < $this->min_service_months) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function scopeEncashable($query)
    {
        return $query->where('is_encashable', true);
    }
}

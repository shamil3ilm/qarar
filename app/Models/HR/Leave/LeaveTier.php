<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LeaveTier extends Model
{
    use HasFactory;
    public const ENTITLEMENT_YEARLY = 'yearly';
    public const ENTITLEMENT_MONTHLY = 'monthly';

    protected $fillable = [
        'leave_type_id',
        'name',
        'description',
        'min_service_months',
        'max_service_months',
        'employee_grade',
        'department_id',
        'entitled_days',
        'entitlement_period',
        'monthly_accrual_rate',
        'max_carryforward_days',
        'carryforward_expiry_months',
        'max_encashable_days',
        'encashment_rate',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_service_months' => 'integer',
            'max_service_months' => 'integer',
            'entitled_days' => 'decimal:2',
            'monthly_accrual_rate' => 'decimal:2',
            'max_carryforward_days' => 'integer',
            'carryforward_expiry_months' => 'integer',
            'max_encashable_days' => 'integer',
            'encashment_rate' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(LeaveTierApprover::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    public function scopeForServiceMonths($query, int $months)
    {
        return $query->where('min_service_months', '<=', $months)
            ->where(function ($q) use ($months) {
                $q->whereNull('max_service_months')
                    ->orWhere('max_service_months', '>=', $months);
            });
    }
}

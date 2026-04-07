<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EosbSettlement extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'eosb_settlements';

    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'termination_date' => 'date',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'years_of_service' => 'decimal:4',
            'total_days_earned' => 'decimal:4',
            'daily_rate' => 'decimal:4',
            'gross_amount' => 'decimal:4',
            'deductions' => 'decimal:4',
            'net_amount' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(EosbPolicy::class, 'eosb_policy_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}

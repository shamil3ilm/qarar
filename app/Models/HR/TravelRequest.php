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

class TravelRequest extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_DOMESTIC      = 'domestic';
    public const TYPE_INTERNATIONAL = 'international';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'request_number',
        'purpose',
        'departure_date',
        'return_date',
        'destination_country',
        'destination_city',
        'travel_type',
        'estimated_cost',
        'advance_requested',
        'advance_approved',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'departure_date'    => 'date',
            'return_date'       => 'date',
            'approved_at'       => 'datetime',
            'estimated_cost'    => 'decimal:4',
            'advance_requested' => 'decimal:4',
            'advance_approved'  => 'decimal:4',
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

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(TravelExpenseClaim::class, 'travel_request_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getTripDays(): int
    {
        if ($this->departure_date === null || $this->return_date === null) {
            return 0;
        }

        return (int) $this->departure_date->diffInDays($this->return_date) + 1;
    }
}

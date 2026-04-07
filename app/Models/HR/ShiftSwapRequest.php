<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSwapRequest extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'shift_swap_requests';

    protected $guarded = ['id'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'requester_shift_date' => 'date',
            'requested_shift_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function requestedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_employee_id');
    }

    public function requesterRosterLine(): BelongsTo
    {
        return $this->belongsTo(ShiftRosterLine::class, 'requester_roster_line_id');
    }

    public function requestedRosterLine(): BelongsTo
    {
        return $this->belongsTo(ShiftRosterLine::class, 'requested_roster_line_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}

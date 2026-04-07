<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccountRequest extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const TYPE_OPEN             = 'open';
    public const TYPE_CLOSE            = 'close';
    public const TYPE_MODIFY           = 'modify';
    public const TYPE_ADD_SIGNATORY    = 'add_signatory';
    public const TYPE_REMOVE_SIGNATORY = 'remove_signatory';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXECUTED = 'executed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'approved_at'  => 'datetime',
            'rejected_at'  => 'datetime',
            'executed_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('request_type', $type);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeExecuted(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}

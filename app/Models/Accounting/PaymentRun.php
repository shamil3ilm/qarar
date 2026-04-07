<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasStateMachine;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRun extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasStateMachine;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date'    => 'date',
            'due_date_from'   => 'date',
            'due_date_to'     => 'date',
            'vendor_filter'   => 'array',
            'payment_methods' => 'array',
            'minimum_payment' => 'decimal:4',
            'total_amount'    => 'decimal:4',
            'total_items'     => 'integer',
        ];
    }

    public const DIRECTION_OUTGOING = 'outgoing';
    public const DIRECTION_INCOMING = 'incoming';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PROPOSED  = 'proposed';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public function items(): HasMany
    {
        return $this->hasMany(PaymentRunItem::class);
    }

    public function includedItems(): HasMany
    {
        return $this->hasMany(PaymentRunItem::class)->where('status', PaymentRunItem::STATUS_INCLUDED);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_POSTED]);
    }

    // -------------------------------------------------------------------------
    // HasStateMachine implementation
    // -------------------------------------------------------------------------

    protected function getStateColumn(): string
    {
        return 'status';
    }

    protected function getStateTransitions(): array
    {
        return [
            self::STATUS_DRAFT     => [self::STATUS_PROPOSED, self::STATUS_CANCELLED],
            self::STATUS_PROPOSED  => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED  => [self::STATUS_POSTED,   self::STATUS_CANCELLED],
            self::STATUS_POSTED    => [],
            self::STATUS_CANCELLED => [],
        ];
    }
}

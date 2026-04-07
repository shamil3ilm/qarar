<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashVoucher extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'petty_cash_vouchers';

    protected $guarded = ['id'];

    // Status constants
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    // Type constants
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_PAYMENT = 'payment';

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount'       => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'account_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Transition to a new status, optionally merging extra fields.
     */
    public function transitionTo(string $status, array $extra = []): void
    {
        $this->update(array_merge(['status' => $status], $extra));
    }
}

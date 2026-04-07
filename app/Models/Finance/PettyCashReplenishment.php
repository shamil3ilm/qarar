<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashReplenishment extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'petty_cash_replenishments';

    protected $guarded = ['id'];

    // Status constants
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_DISBURSED = 'disbursed';

    protected function casts(): array
    {
        return [
            'replenishment_date' => 'date',
            'amount'             => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    public function isRequested(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDisbursed(): bool
    {
        return $this->status === self::STATUS_DISBURSED;
    }

    /**
     * Transition to a new status, optionally merging extra fields.
     */
    public function transitionTo(string $status, array $extra = []): void
    {
        $this->update(array_merge(['status' => $status], $extra));
    }
}

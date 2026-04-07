<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetTransfer extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const TYPE_BOOK_VALUE        = 'book_value';
    public const TYPE_GROSS_VALUE       = 'gross_value';
    public const TYPE_NEGOTIATED_PRICE  = 'negotiated_price';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transfer_date'            => 'date',
            'gross_value'              => 'decimal:4',
            'accumulated_depreciation' => 'decimal:4',
            'net_book_value'           => 'decimal:4',
            'transfer_price'           => 'decimal:4',
            'gain_loss_amount'         => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function sendingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'sending_organization_id');
    }

    public function receivingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'receiving_organization_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function receivingAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'receiving_asset_id');
    }

    public function sendingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'sending_journal_id');
    }

    public function receivingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'receiving_journal_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Effective transfer value for the receiving org. */
    public function effectiveTransferValue(): float
    {
        return match ($this->transfer_type) {
            self::TYPE_NEGOTIATED_PRICE => (float) ($this->transfer_price ?? $this->net_book_value),
            self::TYPE_GROSS_VALUE      => (float) $this->gross_value,
            default                     => (float) $this->net_book_value,
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}

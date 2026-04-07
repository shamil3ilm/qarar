<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ForexGainLossEntry extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    // Entry type constants
    public const TYPE_REALIZED = 'realized';
    public const TYPE_UNREALIZED = 'unrealized';

    // Transaction type constants
    public const TRANSACTION_PAYMENT = 'payment';
    public const TRANSACTION_RECEIPT = 'receipt';
    public const TRANSACTION_TRANSFER = 'transfer';
    public const TRANSACTION_REVALUATION = 'revaluation';

    protected $fillable = [
        'organization_id',
        'entry_type',
        'transaction_type',
        'source_type',
        'source_id',
        'foreign_currency',
        'base_currency',
        'foreign_amount',
        'original_rate',
        'settlement_rate',
        'gain_loss_amount',
        'account_id',
        'journal_entry_id',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'foreign_amount' => 'decimal:4',
            'original_rate' => 'decimal:8',
            'settlement_rate' => 'decimal:8',
            'gain_loss_amount' => 'decimal:4',
            'transaction_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRealized(Builder $query): Builder
    {
        return $query->where('entry_type', self::TYPE_REALIZED);
    }

    public function scopeUnrealized(Builder $query): Builder
    {
        return $query->where('entry_type', self::TYPE_UNREALIZED);
    }

    public function scopeGains(Builder $query): Builder
    {
        return $query->where('gain_loss_amount', '>', 0);
    }

    public function scopeLosses(Builder $query): Builder
    {
        return $query->where('gain_loss_amount', '<', 0);
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('foreign_currency', $currencyCode);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    public function scopeForTransactionType(Builder $query, string $type): Builder
    {
        return $query->where('transaction_type', $type);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isGain(): bool
    {
        return (float) $this->gain_loss_amount > 0;
    }

    public function isLoss(): bool
    {
        return (float) $this->gain_loss_amount < 0;
    }
}

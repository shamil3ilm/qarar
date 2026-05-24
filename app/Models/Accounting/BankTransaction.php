<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class BankTransaction extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_UNMATCHED   = 'unmatched';
    public const STATUS_MATCHED     = 'matched';
    public const STATUS_EXCLUDED    = 'excluded';
    public const STATUS_RECONCILED  = 'reconciled';

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'transaction_date',
        'value_date',
        'reference',
        'description',
        'transaction_type',
        'amount',
        'balance',
        'status',
        'category',
        'matched_transaction_id',
        'matched_transaction_type',
        'matched_by',
        'matched_at',
        'import_source',
        'import_batch_id',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date'       => 'date',
            'amount'           => 'decimal:4',
            'balance'          => 'decimal:4',
            'matched_at'       => 'datetime',
            'raw_data'         => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (BankTransaction $transaction) {
            // Update bank account balance
            $transaction->bankAccount->recalculateBalance();
        });

        static::deleted(function (BankTransaction $transaction) {
            $transaction->bankAccount->recalculateBalance();
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_line_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}

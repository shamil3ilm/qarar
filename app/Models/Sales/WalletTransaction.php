<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory, HasUuid;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'source_type',
        'source_id',
        'reference_number',
        'transaction_date',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            if (empty($transaction->transaction_date)) {
                $transaction->transaction_date = now()->toDateString();
            }
            if (empty($transaction->created_by) && auth()->check()) {
                $transaction->created_by = auth()->id();
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCredits($query)
    {
        return $query->where('transaction_type', self::TYPE_CREDIT);
    }

    public function scopeDebits($query)
    {
        return $query->where('transaction_type', self::TYPE_DEBIT);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    protected $fillable = [
        'organization_id',
        'account_id',
        'balance',
        'debit_total',
        'credit_total',
        'computed_at',
    ];

    protected $casts = [
        'balance'      => 'decimal:4',
        'debit_total'  => 'decimal:4',
        'credit_total' => 'decimal:4',
        'computed_at'  => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Upsert the snapshot for an account.
     */
    public static function upsertForAccount(int $orgId, int $accountId, array $data): void
    {
        static::updateOrCreate(
            ['organization_id' => $orgId, 'account_id' => $accountId],
            array_merge($data, ['computed_at' => now()])
        );
    }
}

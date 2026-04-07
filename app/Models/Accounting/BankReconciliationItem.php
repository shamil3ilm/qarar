<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Accounting\BankTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationItem extends Model
{
    use HasFactory;

    public const TYPE_BANK_TRANSACTION = 'bank_transaction';
    public const TYPE_JOURNAL_ENTRY    = 'journal_entry';

    protected $guarded = ['id'];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EliminationEntry extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_INTERCOMPANY_RECEIVABLE = 'intercompany_receivable';
    public const TYPE_INTERCOMPANY_PAYABLE    = 'intercompany_payable';
    public const TYPE_DIVIDEND                = 'dividend';
    public const TYPE_INVESTMENT              = 'investment';
    public const TYPE_OTHER                   = 'other';

    protected $fillable = [
        'organization_id',
        'consolidation_period_id',
        'entry_type',
        'description',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'currency_code',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ConsolidationPeriod::class, 'consolidation_period_id');
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

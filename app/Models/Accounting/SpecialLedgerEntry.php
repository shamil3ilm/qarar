<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialLedgerEntry extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'special_ledger_id',
        'journal_entry_id',
        'account_id',
        'posting_date',
        'amount',
        'currency_code',
        'exchange_rate',
        'amount_local',
        'debit_credit',
        'period',
        'fiscal_year',
        'cost_center_id',
        'profit_center_id',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'posting_date'  => 'date',
            'amount'        => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'amount_local'  => 'decimal:4',
            'period'        => 'integer',
            'fiscal_year'   => 'integer',
        ];
    }

    public function specialLedger(): BelongsTo
    {
        return $this->belongsTo(SpecialLedger::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class);
    }
}

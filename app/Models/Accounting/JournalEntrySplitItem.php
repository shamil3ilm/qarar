<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntrySplitItem extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'original_line_id',
        'profit_center_id',
        'cost_center_id',
        'debit_amount',
        'credit_amount',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount'  => 'decimal:4',
            'credit_amount' => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date'          => 'date',
            'opening_balance'       => 'decimal:4',
            'payment_amount'        => 'decimal:4',
            'interest_portion'      => 'decimal:4',
            'principal_portion'     => 'decimal:4',
            'closing_balance'       => 'decimal:4',
            'rou_depreciation'      => 'decimal:4',
            'is_posted'             => 'boolean',
        ];
    }

    public function leaseContract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

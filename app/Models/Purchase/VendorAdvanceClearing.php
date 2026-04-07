<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdvanceClearing extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cleared_amount' => 'decimal:4',
            'clearing_date' => 'date',
        ];
    }

    public function advancePayment(): BelongsTo
    {
        return $this->belongsTo(VendorAdvancePayment::class, 'advance_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

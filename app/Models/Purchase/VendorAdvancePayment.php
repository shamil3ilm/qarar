<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorAdvancePayment extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(VendorAdvanceRequest::class, 'advance_request_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function clearings(): HasMany
    {
        return $this->hasMany(VendorAdvanceClearing::class, 'advance_payment_id');
    }

    public function getClearedAmount(): float
    {
        return (float) $this->clearings()->sum('cleared_amount');
    }

    public function getUnclearedAmount(): float
    {
        return (float) bcsub((string) $this->amount, (string) $this->getClearedAmount(), 4);
    }

    public function isFullyCleared(): bool
    {
        return $this->getUnclearedAmount() <= 0;
    }
}

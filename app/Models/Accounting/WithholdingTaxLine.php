<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingTaxLine extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const TYPE_PAYMENT_MADE     = 'payment_made';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gross_amount'     => 'decimal:4',
            'wht_rate'         => 'decimal:4',
            'wht_amount'       => 'decimal:4',
            'net_amount'       => 'decimal:4',
            'transaction_date' => 'date',
            'certificate_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function whtCode(): BelongsTo
    {
        return $this->belongsTo(WithholdingTaxCode::class, 'wht_code_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    public function scopeUncertified($query)
    {
        return $query->whereNull('certificate_number');
    }
}

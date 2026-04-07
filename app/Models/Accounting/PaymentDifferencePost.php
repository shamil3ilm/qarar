<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDifferencePost extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const TYPE_UNDERPAYMENT = 'underpayment';
    public const TYPE_OVERPAYMENT  = 'overpayment';

    public const RES_WRITTEN_OFF  = 'written_off';
    public const RES_CREDITED     = 'credited';
    public const RES_AUTO_CLEARED = 'auto_cleared';

    public const PAYMENT_RECEIVED = 'payment_received';
    public const PAYMENT_MADE     = 'payment_made';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'invoice_amount'    => 'decimal:4',
            'payment_amount'    => 'decimal:4',
            'difference_amount' => 'decimal:4',
            'posting_date'      => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function toleranceGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentToleranceGroup::class, 'tolerance_group_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('posting_date', [$from, $to]);
    }
}

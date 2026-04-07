<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\Bill;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class DebitNote extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'debit_note_number',
        'bill_id',
        'supplier_id',
        'debit_note_date',
        'currency_code',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'total',
        'applied_amount',
        'available_amount',
        'reason',
        'notes',
        'status',
        'journal_entry_id',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'debit_note_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'available_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DebitNoteItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function applyToBill(Bill $bill, float $amount): void
    {
        $amountToApply = min($amount, (float) $this->available_amount, (float) $bill->amount_due);

        $this->applied_amount = bcadd((string) $this->applied_amount, (string) $amountToApply, 2);
        $this->available_amount = bcsub((string) $this->available_amount, (string) $amountToApply, 2);

        if (bccomp((string) $this->available_amount, '0', 2) <= 0) {
            $this->status = self::STATUS_APPLIED;
        }

        $this->save();
    }

    public function hasAvailableBalance(): bool
    {
        return bccomp((string) $this->available_amount, '0', 2) > 0;
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_APPLIED])
            ->where('available_amount', '>', 0);
    }
}

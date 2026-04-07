<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasFactory, BelongsToOrganization, HasAuditTrail, HasUuid, SoftDeletes;

    public const TYPE_SALES = 'sales';
    public const TYPE_PURCHASE = 'purchase';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'credit_note_number',
        'credit_note_type',
        'invoice_id',
        'contact_id',
        'credit_note_date',
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
            'credit_note_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'available_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'approved_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applyToInvoice(Invoice $invoice, float $amount): CreditNoteApplication
    {
        if ($invoice->organization_id !== $this->organization_id) {
            throw new \InvalidArgumentException('Credit note and invoice must belong to the same organization.');
        }

        $amountToApply = min($amount, (float) $this->available_amount, (float) $invoice->amount_due);

        $this->applied_amount = bcadd((string) $this->applied_amount, (string) $amountToApply, 2);
        $this->available_amount = bcsub((string) $this->available_amount, (string) $amountToApply, 2);

        if (bccomp((string) $this->available_amount, '0', 2) <= 0) {
            $this->status = self::STATUS_APPLIED;
        }

        $this->save();

        return $this->applications()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amountToApply,
            'applied_date' => now(),
        ]);
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

    public function scopeSalesType($query)
    {
        return $query->where('credit_note_type', self::TYPE_SALES);
    }

    public function scopePurchaseType($query)
    {
        return $query->where('credit_note_type', self::TYPE_PURCHASE);
    }
}

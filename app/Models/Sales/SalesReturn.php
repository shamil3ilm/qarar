<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_REFUND = 'refund';
    public const TYPE_EXCHANGE = 'exchange';
    public const TYPE_CREDIT_NOTE = 'credit_note';
    public const TYPE_REPLACEMENT = 'replacement';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const INSPECTION_PENDING = 'pending';
    public const INSPECTION_PASSED = 'passed';
    public const INSPECTION_FAILED = 'failed';
    public const INSPECTION_PARTIAL = 'partial';

    public const RESOLUTION_FULL_REFUND = 'full_refund';
    public const RESOLUTION_PARTIAL_REFUND = 'partial_refund';
    public const RESOLUTION_EXCHANGE = 'exchange';
    public const RESOLUTION_CREDIT_NOTE = 'credit_note';
    public const RESOLUTION_REPLACEMENT = 'replacement';
    public const RESOLUTION_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'return_number',
        'customer_id',
        'invoice_id',
        'sales_order_id',
        'return_date',
        'return_reason_id',
        'reason_notes',
        'return_type',
        'currency_code',
        'subtotal',
        'tax_amount',
        'restocking_fee',
        'total',
        'refund_amount',
        'status',
        'inspection_status',
        'inspection_notes',
        'resolution_type',
        'credit_note_id',
        'refund_id',
        'exchange_order_id',
        'warehouse_id',
        'restock_items',
        'items_received',
        'received_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'restocking_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'restock_items' => 'boolean',
            'items_received' => 'boolean',
            'received_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function exchangeOrder(): HasOne
    {
        return $this->hasOne(ExchangeOrder::class);
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

    public function approve(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'resolution_type' => self::RESOLUTION_REJECTED,
        ]);
    }

    public function markReceived(): void
    {
        $this->update([
            'status' => self::STATUS_RECEIVED,
            'items_received' => true,
            'received_at' => now(),
        ]);
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('subtotal');
        $taxAmount = $this->items->sum('tax_amount');
        $total = bcsub(bcadd((string) $subtotal, (string) $taxAmount, 2), (string) $this->restocking_fee, 2);

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

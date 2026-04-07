<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\BankAccount;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkSaleBatch extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'batch_number',
        'name',
        'sale_date',
        'original_sale_date',
        'currency_code',
        'total_invoices',
        'total_subtotal',
        'total_discount',
        'total_tax',
        'total_amount',
        'status',
        'processed_count',
        'success_count',
        'failed_count',
        'errors',
        'started_at',
        'completed_at',
        'auto_post',
        'auto_send_email',
        'generate_receipts',
        'payment_method',
        'bank_account_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'original_sale_date' => 'date',
            'total_invoices' => 'integer',
            'total_subtotal' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'processed_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'auto_post' => 'boolean',
            'auto_send_email' => 'boolean',
            'generate_receipts' => 'boolean',
        ];
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkSaleItem::class, 'batch_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Business logic
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIALLY_COMPLETED], true);
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PROCESSING], true);
    }

    public function isBackdated(): bool
    {
        return $this->original_sale_date !== null
            && $this->original_sale_date->ne($this->sale_date);
    }

    /**
     * Recalculate batch totals from items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->update([
            'total_invoices' => $items->count(),
            'total_subtotal' => $items->sum(fn ($item) => bcmul((string) $item->quantity, (string) $item->unit_price, 2)),
            'total_discount' => $items->sum('discount_amount'),
            'total_tax' => $items->sum('tax_amount'),
            'total_amount' => $items->sum('total_amount'),
        ]);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_PARTIALLY_COMPLETED]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}

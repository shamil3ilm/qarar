<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LandedCostVoucher extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    // Allocation method constants
    public const ALLOCATION_VALUE = 'value';
    public const ALLOCATION_QUANTITY = 'quantity';
    public const ALLOCATION_WEIGHT = 'weight';
    public const ALLOCATION_VOLUME = 'volume';
    public const ALLOCATION_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'voucher_number',
        'voucher_date',
        'purchase_order_id',
        'shipment_id',
        'bill_id',
        'currency_code',
        'exchange_rate',
        'total_purchase_value',
        'total_additional_charges',
        'total_landed_cost',
        'allocation_method',
        'status',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'exchange_rate' => 'decimal:8',
            'total_purchase_value' => 'decimal:4',
            'total_additional_charges' => 'decimal:4',
            'total_landed_cost' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->voucher_number)) {
                $model->voucher_number = static::generateNumber($model->organization_id);
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ImportExportShipment::class, 'shipment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandedCostItem::class, 'voucher_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LandedCostCharge::class, 'voucher_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->items()->count() > 0
            && $this->charges()->count() > 0;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_POSTED]);
    }

    public function recalculateTotals(): void
    {
        $this->total_purchase_value = $this->items()->sum('purchase_value');
        $this->total_additional_charges = $this->charges()->sum('base_amount');
        $this->total_landed_cost = (float) $this->total_purchase_value + (float) $this->total_additional_charges;

        $this->saveQuietly();
    }

    public static function generateNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $key = "LCV-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('voucher_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('voucher_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}

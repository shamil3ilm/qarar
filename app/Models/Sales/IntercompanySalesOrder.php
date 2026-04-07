<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\TransferPriceVersion;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntercompanySalesOrder extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_CONFIRMED   = 'confirmed';
    public const STATUS_IN_DELIVERY = 'in_delivery';
    public const STATUS_BILLED      = 'billed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'selling_organization_id',
        'buying_organization_id',
        'sales_order_id',
        'order_number',
        'status',
        'order_date',
        'requested_delivery_date',
        'transfer_price_version_id',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date'              => 'date',
            'requested_delivery_date' => 'date',
            'status'                  => 'string',
            'subtotal'                => 'decimal:4',
            'tax_amount'              => 'decimal:4',
            'total_amount'            => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function sellingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'selling_organization_id');
    }

    public function buyingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buying_organization_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function transferPriceVersion(): BelongsTo
    {
        return $this->belongsTo(TransferPriceVersion::class, 'transfer_price_version_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(IntercompanySalesOrderLine::class);
    }

    public function purchaseOrderLink(): HasOne
    {
        return $this->hasOne(IcPurchaseOrderLink::class);
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(IcBillingDocument::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForSellingOrg(Builder $query, int $orgId): Builder
    {
        return $query->where('selling_organization_id', $orgId);
    }

    public function scopeForBuyingOrg(Builder $query, int $orgId): Builder
    {
        return $query->where('buying_organization_id', $orgId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function recalculateTotals(): void
    {
        $subtotal  = (string) $this->lines()->sum('line_total');
        $taxAmount = (string) $this->lines()->sum('tax_amount');
        $total     = bcadd($subtotal, $taxAmount, 4);

        $this->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => $total,
        ]);
    }

    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED], true);
    }

    public function canBill(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_IN_DELIVERY], true);
    }
}

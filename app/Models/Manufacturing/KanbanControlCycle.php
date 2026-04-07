<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanControlCycle extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STRATEGY_PRODUCTION      = 'production';
    public const STRATEGY_PURCHASE        = 'purchase';
    public const STRATEGY_STOCK_TRANSFER  = 'stock_transfer';

    protected $fillable = [
        'organization_id',
        'product_id',
        'supply_area_id',
        'replenishment_strategy',
        'number_of_cards',
        'replenishment_quantity',
        'safety_stock_quantity',
        'replenishment_lead_time_days',
        'source_vendor_id',
        'source_warehouse_id',
        'is_active',
    ];

    protected $casts = [
        'number_of_cards'              => 'integer',
        'replenishment_quantity'       => 'decimal:4',
        'safety_stock_quantity'        => 'decimal:4',
        'replenishment_lead_time_days' => 'integer',
        'is_active'                    => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplyArea(): BelongsTo
    {
        return $this->belongsTo(KanbanSupplyArea::class, 'supply_area_id');
    }

    public function sourceVendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'source_vendor_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'control_cycle_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isProductionStrategy(): bool
    {
        return $this->replenishment_strategy === self::STRATEGY_PRODUCTION;
    }

    public function isPurchaseStrategy(): bool
    {
        return $this->replenishment_strategy === self::STRATEGY_PURCHASE;
    }

    public function isStockTransferStrategy(): bool
    {
        return $this->replenishment_strategy === self::STRATEGY_STOCK_TRANSFER;
    }

    /**
     * Count cards currently in a given status.
     */
    public function countByStatus(string $status): int
    {
        return $this->cards()->where('status', $status)->count();
    }
}

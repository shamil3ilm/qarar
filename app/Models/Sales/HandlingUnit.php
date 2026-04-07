<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HandlingUnit extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'shipment_id',
        'sales_order_id',
        'hu_type',
        'hu_number',
        'sscc_number',
        'gross_weight',
        'net_weight',
        'volume',
        'length',
        'width',
        'height',
        'is_sealed',
        'notes',
    ];

    protected $casts = [
        'gross_weight' => 'decimal:4',
        'net_weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_sealed' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandlingUnitItem::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForShipment(Builder $query, int $shipmentId): Builder
    {
        return $query->where('shipment_id', $shipmentId);
    }

    public function scopeForSalesOrder(Builder $query, int $salesOrderId): Builder
    {
        return $query->where('sales_order_id', $salesOrderId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getTotalItems(): int
    {
        return $this->items()->count();
    }

    public function seal(): self
    {
        $this->update(['is_sealed' => true]);
        return $this->fresh();
    }
}

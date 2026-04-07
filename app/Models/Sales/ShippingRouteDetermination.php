<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRouteDetermination extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'sales_order_id',
        'shipment_id',
        'shipping_route_id',
        'departure_zone_id',
        'destination_zone_id',
        'determined_at',
    ];

    protected $casts = [
        'determined_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shippingRoute(): BelongsTo
    {
        return $this->belongsTo(ShippingRoute::class);
    }

    public function departureZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'departure_zone_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'destination_zone_id');
    }
}

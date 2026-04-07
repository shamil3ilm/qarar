<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportationOrder extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_transportation_orders';

    protected $fillable = [
        'organization_id',
        'order_number',
        'type',
        'status',
        'carrier_id',
        'carrier_service_id',
        'load_plan_id',
        'tender_request_id',
        'origin_address',
        'origin_country',
        'destination_address',
        'destination_country',
        'planned_departure',
        'planned_arrival',
        'actual_departure',
        'actual_arrival',
        'total_weight',
        'total_volume',
        'freight_cost',
        'currency_code',
        'tracking_number',
        'has_dangerous_goods',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'planned_departure' => 'datetime',
        'planned_arrival' => 'datetime',
        'actual_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'total_weight' => 'decimal:3',
        'total_volume' => 'decimal:4',
        'freight_cost' => 'decimal:4',
        'has_dangerous_goods' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function carrierService(): BelongsTo
    {
        return $this->belongsTo(CarrierService::class, 'carrier_service_id');
    }

    public function loadPlan(): BelongsTo
    {
        return $this->belongsTo(LoadPlan::class, 'load_plan_id');
    }

    public function tenderRequest(): BelongsTo
    {
        return $this->belongsTo(FreightTenderRequest::class, 'tender_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportationOrderItem::class, 'transportation_order_id');
    }
}

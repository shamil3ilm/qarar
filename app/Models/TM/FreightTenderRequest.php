<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreightTenderRequest extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_freight_tender_requests';

    protected $fillable = [
        'organization_id',
        'tender_number',
        'title',
        'origin_country',
        'origin_zone',
        'destination_country',
        'destination_zone',
        'transport_mode',
        'total_weight',
        'total_volume',
        'shipment_count',
        'has_dangerous_goods',
        'requires_refrigeration',
        'required_by_date',
        'bid_deadline',
        'status',
        'awarded_carrier_id',
        'awarded_bid_id',
        'awarded_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'total_weight' => 'decimal:3',
        'total_volume' => 'decimal:4',
        'shipment_count' => 'integer',
        'has_dangerous_goods' => 'boolean',
        'requires_refrigeration' => 'boolean',
        'required_by_date' => 'date',
        'bid_deadline' => 'datetime',
        'awarded_at' => 'datetime',
    ];

    public function awardedCarrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'awarded_carrier_id');
    }

    public function awardedBid(): BelongsTo
    {
        return $this->belongsTo(FreightTenderBid::class, 'awarded_bid_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FreightTenderItem::class, 'tender_request_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(FreightTenderBid::class, 'tender_request_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isAwarded(): bool
    {
        return $this->status === 'awarded';
    }
}

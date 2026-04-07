<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'status', 'description', 'location', 'event_at', 'raw_data',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}

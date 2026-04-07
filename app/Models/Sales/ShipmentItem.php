<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'product_id', 'variant_id', 'quantity', 'weight_kg', 'serial_numbers',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'weight_kg' => 'decimal:2',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }
}

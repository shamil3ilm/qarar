<?php

declare(strict_types=1);

namespace App\Models\TM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportationOrderItem extends Model
{
    protected $table = 'tm_transportation_order_items';

    protected $fillable = [
        'transportation_order_id',
        'reference_type',
        'reference_id',
        'reference_number',
        'product_id',
        'description',
        'quantity',
        'unit_of_measure',
        'weight',
        'volume',
        'is_dangerous_goods',
        'un_number',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'weight' => 'decimal:3',
        'volume' => 'decimal:4',
        'is_dangerous_goods' => 'boolean',
    ];

    public function transportationOrder(): BelongsTo
    {
        return $this->belongsTo(TransportationOrder::class, 'transportation_order_id');
    }
}

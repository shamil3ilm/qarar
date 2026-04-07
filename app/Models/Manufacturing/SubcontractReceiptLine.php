<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractReceiptLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'quantity_rejected' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'total_cost'        => 'decimal:4',
        'expiry_date'       => 'date',
    ];

    // Relationships

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(SubcontractReceipt::class, 'receipt_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrderLine::class, 'order_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    // Helpers

    public function getAcceptedQuantity(): float
    {
        return max(0.0, (float) $this->quantity_received - (float) $this->quantity_rejected);
    }
}

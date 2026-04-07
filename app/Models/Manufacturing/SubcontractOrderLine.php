<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractOrderLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'ordered_quantity'    => 'decimal:4',
        'received_quantity'   => 'decimal:4',
        'unit_service_charge' => 'decimal:4',
        'total_service_charge'=> 'decimal:4',
        'scrap_quantity'      => 'decimal:4',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(SubcontractReceiptLine::class, 'order_line_id');
    }

    // Helpers

    public function getRemainingQuantity(): float
    {
        return max(0.0, (float) $this->ordered_quantity - (float) $this->received_quantity);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->received_quantity >= (float) $this->ordered_quantity;
    }
}

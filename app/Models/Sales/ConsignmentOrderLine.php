<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentOrderLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate'   => 'decimal:2',
            'line_total' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ConsignmentOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Calculate and store the line total based on quantity, unit price and tax rate.
     */
    public function calculateTotal(): void
    {
        if ($this->unit_price === null) {
            return;
        }

        $subtotal = bcmul((string) $this->quantity, (string) $this->unit_price, 4);
        $taxAmount = bcmul($subtotal, bcdiv((string) $this->tax_rate, '100', 6), 4);
        $this->line_total = bcadd($subtotal, $taxAmount, 4);
    }
}

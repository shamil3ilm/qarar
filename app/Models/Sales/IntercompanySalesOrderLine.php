<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercompanySalesOrderLine extends Model
{
    use HasUuid;

    protected $fillable = [
        'intercompany_sales_order_id',
        'product_id',
        'line_number',
        'description',
        'quantity',
        'unit_of_measure',
        'transfer_price',
        'list_price',
        'tax_rate',
        'tax_amount',
        'line_total',
        'delivered_quantity',
        'billed_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity'           => 'decimal:4',
            'transfer_price'     => 'decimal:4',
            'list_price'         => 'decimal:4',
            'tax_rate'           => 'decimal:2',
            'tax_amount'         => 'decimal:4',
            'line_total'         => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'billed_quantity'    => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(IntercompanySalesOrder::class, 'intercompany_sales_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function computeLineTotal(): float
    {
        return (float) bcmul((string) $this->quantity, (string) $this->transfer_price, 4);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSaleLine extends Model
{
    protected $fillable = [
        'cash_sale_id',
        'product_id',
        'quantity',
        'uom',
        'unit_price',
        'discount_pct',
        'line_total',
        'tax_amount',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'unit_price'   => 'decimal:4',
        'discount_pct' => 'decimal:4',
        'line_total'   => 'decimal:4',
        'tax_amount'   => 'decimal:4',
    ];

    public function cashSale(): BelongsTo
    {
        return $this->belongsTo(CashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactPurchase extends Model
{
    protected $table = 'fact_purchases';

    protected $fillable = [
        'organization_id',
        'dim_product_id',
        'dim_vendor_id',
        'dim_time_id',
        'dim_warehouse_id',
        'purchase_order_id',
        'bill_id',
        'quantity',
        'unit_price',
        'net_amount',
        'tax_amount',
        'gross_amount',
        'currency_code',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'unit_price'   => 'decimal:4',
        'net_amount'   => 'decimal:4',
        'tax_amount'   => 'decimal:4',
        'gross_amount' => 'decimal:4',
    ];

    public function dimProduct(): BelongsTo
    {
        return $this->belongsTo(DimProduct::class);
    }

    public function dimVendor(): BelongsTo
    {
        return $this->belongsTo(DimVendor::class);
    }

    public function dimTime(): BelongsTo
    {
        return $this->belongsTo(DimTime::class);
    }

    public function dimWarehouse(): BelongsTo
    {
        return $this->belongsTo(DimWarehouse::class);
    }
}

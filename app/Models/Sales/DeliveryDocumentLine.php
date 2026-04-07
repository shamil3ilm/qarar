<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryDocumentLine extends Model
{
    protected $fillable = [
        'delivery_document_id',
        'sales_order_line_id',
        'product_id',
        'delivery_quantity',
        'picked_quantity',
        'packed_quantity',
        'issued_quantity',
        'uom',
        'batch_number',
        'warehouse_location_id',
    ];

    protected $casts = [
        'delivery_quantity' => 'decimal:4',
        'picked_quantity'   => 'decimal:4',
        'packed_quantity'   => 'decimal:4',
        'issued_quantity'   => 'decimal:4',
    ];

    public function deliveryDocument(): BelongsTo
    {
        return $this->belongsTo(DeliveryDocument::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }
}

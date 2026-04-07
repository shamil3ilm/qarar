<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandlingUnitItem extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'handling_unit_id',
        'product_id',
        'inventory_batch_id',
        'sales_order_line_id',
        'quantity',
        'weight',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'weight' => 'decimal:4',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function handlingUnit(): BelongsTo
    {
        return $this->belongsTo(HandlingUnit::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}

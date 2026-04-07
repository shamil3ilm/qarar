<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderCoProductActual extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'work_order_id',
        'bom_co_product_id',
        'product_id',
        'co_product_type',
        'planned_quantity',
        'actual_quantity',
        'unit_of_measure',
        'warehouse_id',
        'posted_to_stock',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'posted_to_stock' => 'boolean',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function bomCoProduct(): BelongsTo
    {
        return $this->belongsTo(BomCoProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Helpers

    public function getVariance(): float
    {
        return (float) $this->actual_quantity - (float) $this->planned_quantity;
    }
}

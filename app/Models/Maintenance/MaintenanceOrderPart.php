<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrderPart extends Model
{
    protected $fillable = [
        'maintenance_order_id',
        'product_id',
        'description',
        'quantity_required',
        'quantity_used',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'quantity_used'     => 'decimal:4',
            'unit_cost'         => 'decimal:4',
        ];
    }

    // Relations

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Computed helpers

    /**
     * Calculate the total cost for this part line (quantity_used * unit_cost).
     */
    public function getTotalCost(): float
    {
        if ($this->unit_cost === null) {
            return 0.0;
        }

        return (float) $this->quantity_used * (float) $this->unit_cost;
    }
}

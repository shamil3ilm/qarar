<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitValuation extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'inventory_split_valuations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand'    => 'decimal:4',
            'quantity_reserved'   => 'decimal:4',
            'moving_average_price' => 'decimal:6',
            'standard_price'      => 'decimal:6',
            'total_stock_value'   => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function valuationType(): BelongsTo
    {
        return $this->belongsTo(ValuationType::class, 'valuation_type_id');
    }

    /**
     * Available (unreserved) quantity for this valuation split.
     */
    public function getAvailableQuantity(): float
    {
        return max(0, (float) $this->quantity_on_hand - (float) $this->quantity_reserved);
    }

    /**
     * Weighted-average price after receiving qty at receipt_price.
     */
    public function computeNewMap(float $receiptQty, float $receiptPrice): float
    {
        $currentValue = (float) $this->quantity_on_hand * (float) $this->moving_average_price;
        $receiptValue = $receiptQty * $receiptPrice;
        $newQty       = (float) $this->quantity_on_hand + $receiptQty;

        return $newQty > 0 ? ($currentValue + $receiptValue) / $newQty : $receiptPrice;
    }
}

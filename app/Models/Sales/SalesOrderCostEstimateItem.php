<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\CostElement;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderCostEstimateItem extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const CATEGORY_MATERIAL = 'material';
    public const CATEGORY_LABOR    = 'labor';
    public const CATEGORY_OVERHEAD = 'overhead';
    public const CATEGORY_OTHER    = 'other';

    protected $fillable = [
        'organization_id',
        'sales_order_cost_estimate_id',
        'sales_order_line_id',
        'product_id',
        'cost_element_id',
        'cost_category',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:4',
            'cost_per_unit' => 'decimal:4',
            'total_cost'    => 'decimal:4',
            'revenue'       => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(SalesOrderCostEstimate::class, 'sales_order_cost_estimate_id');
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }
}

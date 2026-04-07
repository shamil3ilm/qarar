<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Accounting\CostElement;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostCollectorItem extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const CATEGORY_MATERIAL = 'material';
    public const CATEGORY_LABOR    = 'labor';
    public const CATEGORY_OVERHEAD = 'overhead';
    public const CATEGORY_OTHER    = 'other';

    protected $fillable = [
        'organization_id',
        'product_cost_collector_id',
        'cost_element_id',
        'cost_category',
        'standard_cost',
        'actual_cost',
        'variance',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:4',
            'actual_cost'   => 'decimal:4',
            'variance'      => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function collector(): BelongsTo
    {
        return $this->belongsTo(ProductCostCollector::class, 'product_cost_collector_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }
}

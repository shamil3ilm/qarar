<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VarianceAnalysisItem extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const CATEGORY_PRICE_VARIANCE           = 'price_variance';
    public const CATEGORY_QUANTITY_VARIANCE         = 'quantity_variance';
    public const CATEGORY_EFFICIENCY_VARIANCE       = 'efficiency_variance';
    public const CATEGORY_SPENDING_VARIANCE         = 'spending_variance';
    public const CATEGORY_RESOURCE_USAGE_VARIANCE   = 'resource_usage_variance';
    public const CATEGORY_REMAINING_INPUT_VARIANCE  = 'remaining_input_variance';
    public const CATEGORY_OUTPUT_PRICE_VARIANCE     = 'output_price_variance';
    public const CATEGORY_MIXED_PRICE_VARIANCE      = 'mixed_price_variance';

    protected $fillable = [
        'organization_id',
        'variance_analysis_run_id',
        'reference_type',
        'reference_id',
        'cost_element_id',
        'variance_category',
        'standard_cost',
        'actual_cost',
        'variance_amount',
        'variance_percent',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost'    => 'decimal:4',
            'actual_cost'      => 'decimal:4',
            'variance_amount'  => 'decimal:4',
            'variance_percent' => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function run(): BelongsTo
    {
        return $this->belongsTo(VarianceAnalysisRun::class, 'variance_analysis_run_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }
}

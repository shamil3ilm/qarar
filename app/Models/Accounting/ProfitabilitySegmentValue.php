<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitabilitySegmentValue extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'profitability_segment_id',
        'copa_dimension_id',
        'period',
        'fiscal_year',
        'revenue',
        'cost_of_sales',
        'gross_margin',
        'overhead_costs',
        'net_margin',
        'quantity_sold',
    ];

    protected function casts(): array
    {
        return [
            'period'        => 'integer',
            'fiscal_year'   => 'integer',
            'revenue'       => 'decimal:4',
            'cost_of_sales' => 'decimal:4',
            'gross_margin'  => 'decimal:4',
            'overhead_costs'=> 'decimal:4',
            'net_margin'    => 'decimal:4',
            'quantity_sold' => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function segment(): BelongsTo
    {
        return $this->belongsTo(ProfitabilitySegment::class, 'profitability_segment_id');
    }

    public function copaDimension(): BelongsTo
    {
        return $this->belongsTo(CopaDimension::class, 'copa_dimension_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostSplittingResult extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'cost_splitting_rule_id',
        'period',
        'fiscal_year',
        'total_cost',
        'fixed_cost',
        'variable_cost',
        'run_at',
    ];

    protected function casts(): array
    {
        return [
            'period'       => 'integer',
            'fiscal_year'  => 'integer',
            'total_cost'   => 'decimal:4',
            'fixed_cost'   => 'decimal:4',
            'variable_cost'=> 'decimal:4',
            'run_at'       => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CostSplittingRule::class, 'cost_splitting_rule_id');
    }
}

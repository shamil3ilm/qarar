<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopaPlannedLineItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'plan_version_id',
        'organization_id',
        'period',
        'profit_center_id',
        'product_id',
        'contact_id',
        'planned_revenue',
        'planned_cogs',
        'planned_gross_profit',
        'planned_overhead',
        'planned_net_profit',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'period'               => 'integer',
            'planned_revenue'      => 'decimal:4',
            'planned_cogs'         => 'decimal:4',
            'planned_gross_profit' => 'decimal:4',
            'planned_overhead'     => 'decimal:4',
            'planned_net_profit'   => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(CopaPlanVersion::class, 'plan_version_id');
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class);
    }
}

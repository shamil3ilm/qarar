<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopaLineItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'fiscal_year',
        'period',
        'posting_date',
        'source_document_type',
        'source_document_id',
        'profit_center_id',
        'cost_center_id',
        'product_id',
        'contact_id',
        'revenue',
        'cogs',
        'gross_profit',
        'overhead_allocated',
        'net_profit',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'posting_date'      => 'date',
            'period'            => 'integer',
            'revenue'           => 'decimal:4',
            'cogs'              => 'decimal:4',
            'gross_profit'      => 'decimal:4',
            'overhead_allocated' => 'decimal:4',
            'net_profit'        => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }
}

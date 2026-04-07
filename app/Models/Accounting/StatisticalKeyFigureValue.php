<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticalKeyFigureValue extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'statistical_key_figure_id',
        'cost_center_id',
        'profit_center_id',
        'period',
        'fiscal_year',
        'value',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'period'      => 'integer',
            'fiscal_year' => 'integer',
            'value'       => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function statisticalKeyFigure(): BelongsTo
    {
        return $this->belongsTo(StatisticalKeyFigure::class, 'statistical_key_figure_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}

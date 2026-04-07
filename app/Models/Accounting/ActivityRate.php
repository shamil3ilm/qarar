<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRate extends Model
{
    protected $fillable = [
        'activity_type_id',
        'cost_center_id',
        'fiscal_year_id',
        'period',
        'planned_rate',
        'actual_rate',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'planned_rate' => 'decimal:4',
            'actual_rate'  => 'decimal:4',
            'period'       => 'integer',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}

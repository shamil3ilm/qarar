<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoAssessmentCycleReceiver extends Model
{
    protected $fillable = [
        'assessment_cycle_segment_id',
        'receiver_cost_center_id',
        'receiver_profit_center_id',
        'receiver_order_id',
        'fixed_percentage',
    ];

    protected function casts(): array
    {
        return [
            'fixed_percentage' => 'float',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CoAssessmentCycleSegment::class, 'assessment_cycle_segment_id');
    }

    public function receiverCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'receiver_cost_center_id');
    }

    public function receiverProfitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'receiver_profit_center_id');
    }

    public function receiverOrder(): BelongsTo
    {
        return $this->belongsTo(InternalOrder::class, 'receiver_order_id');
    }
}

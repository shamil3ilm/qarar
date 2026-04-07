<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoAssessmentCycleSegment extends Model
{
    use HasUuid;

    public const TRACING_FIXED_PERCENTAGES     = 'fixed_percentages';
    public const TRACING_STATISTICAL_KEY_FIGURE = 'statistical_key_figure';
    public const TRACING_POSTED_AMOUNTS         = 'posted_amounts';

    protected $fillable = [
        'assessment_cycle_id',
        'segment_number',
        'sender_cost_center_id',
        'sender_profit_center_id',
        'sender_cost_element_id',
        'tracing_factor',
        'skf_id',
    ];

    protected function casts(): array
    {
        return [
            'segment_number' => 'integer',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function assessmentCycle(): BelongsTo
    {
        return $this->belongsTo(CoAssessmentCycle::class, 'assessment_cycle_id');
    }

    public function senderCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'sender_cost_center_id');
    }

    public function senderProfitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'sender_profit_center_id');
    }

    public function senderCostElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'sender_cost_element_id');
    }

    public function statisticalKeyFigure(): BelongsTo
    {
        return $this->belongsTo(StatisticalKeyFigure::class, 'skf_id');
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(CoAssessmentCycleReceiver::class, 'assessment_cycle_segment_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoDistributionSegment extends Model
{
    use HasUuid;

    public const TRACING_FIXED_PERCENTAGES      = 'fixed_percentages';
    public const TRACING_STATISTICAL_KEY_FIGURE  = 'statistical_key_figure';
    public const TRACING_POSTED_AMOUNTS          = 'posted_amounts';

    protected $fillable = [
        'distribution_cycle_id',
        'sender_cost_center_id',
        'cost_element_ids',
        'tracing_factor',
        'skf_id',
    ];

    protected function casts(): array
    {
        return [
            'cost_element_ids' => 'array',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function distributionCycle(): BelongsTo
    {
        return $this->belongsTo(CoDistributionCycle::class, 'distribution_cycle_id');
    }

    public function senderCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'sender_cost_center_id');
    }

    public function statisticalKeyFigure(): BelongsTo
    {
        return $this->belongsTo(StatisticalKeyFigure::class, 'skf_id');
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(CoDistributionSegmentReceiver::class, 'distribution_segment_id');
    }
}

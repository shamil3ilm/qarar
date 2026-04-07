<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierScorecard extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'evaluation_period_start',
        'evaluation_period_end',
        'overall_score',
        'quality_score',
        'delivery_score',
        'price_score',
        'service_score',
        'compliance_score',
        'status',
        'notes',
        'evaluated_by',
        'finalized_at',
        'created_by',
    ];

    protected $casts = [
        'evaluation_period_start' => 'date',
        'evaluation_period_end'   => 'date',
        'overall_score'           => 'decimal:2',
        'quality_score'           => 'decimal:2',
        'delivery_score'          => 'decimal:2',
        'price_score'             => 'decimal:2',
        'service_score'           => 'decimal:2',
        'compliance_score'        => 'decimal:2',
        'finalized_at'            => 'datetime',
    ];

    // Relationships

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierScorecardRating::class, 'scorecard_id')
            ->with('criterion');
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    // Business Logic

    /**
     * Calculate overall score as a weighted average across category scores.
     * Uses the active evaluation criteria weights for the organisation.
     */
    public function calculateOverallScore(): float
    {
        $this->loadMissing(['ratings.criterion']);

        if ($this->ratings->isEmpty()) {
            return 0.0;
        }

        $totalWeight  = 0.0;
        $weightedSum  = 0.0;

        foreach ($this->ratings as $rating) {
            $criterion = $rating->criterion;

            if ($criterion === null || !$criterion->is_active) {
                continue;
            }

            $weight      = (float) $criterion->weight_percent;
            $totalWeight += $weight;
            $weightedSum += $weight * (float) $rating->score;
        }

        if ($totalWeight <= 0.0) {
            return 0.0;
        }

        return round($weightedSum / $totalWeight, 2);
    }

    /**
     * Compute per-category average scores from the ratings and persist them.
     * Returns $this for fluent chaining.
     */
    public function finalize(int $userId): self
    {
        $this->loadMissing(['ratings.criterion']);

        $categoryScores = [];

        foreach ($this->ratings as $rating) {
            $category = $rating->criterion?->category ?? 'other';

            if (!isset($categoryScores[$category])) {
                $categoryScores[$category] = ['sum' => 0.0, 'count' => 0];
            }

            $categoryScores[$category]['sum']   += (float) $rating->score;
            $categoryScores[$category]['count'] += 1;
        }

        $average = fn(string $cat): ?float => isset($categoryScores[$cat])
            ? round($categoryScores[$cat]['sum'] / $categoryScores[$cat]['count'], 2)
            : null;

        $this->update([
            'quality_score'    => $average('quality'),
            'delivery_score'   => $average('delivery'),
            'price_score'      => $average('price'),
            'service_score'    => $average('service'),
            'compliance_score' => $average('compliance'),
            'overall_score'    => $this->calculateOverallScore(),
            'status'           => self::STATUS_FINALIZED,
            'evaluated_by'     => $userId,
            'finalized_at'     => now(),
        ]);

        return $this->fresh();
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}

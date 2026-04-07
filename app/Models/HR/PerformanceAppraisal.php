<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceAppraisal extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SELF_REVIEW_SUBMITTED = 'self_review_submitted';
    public const STATUS_MANAGER_REVIEW_SUBMITTED = 'manager_review_submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SELF_REVIEW_SUBMITTED,
        self::STATUS_MANAGER_REVIEW_SUBMITTED,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'organization_id',
        'appraisal_cycle_id',
        'employee_id',
        'reviewer_id',
        'appraisal_template_id',
        'status',
        'self_submitted_at',
        'manager_submitted_at',
        'acknowledged_at',
        'overall_self_rating',
        'overall_manager_rating',
        'final_rating',
        'self_comments',
        'manager_comments',
        'employee_acknowledgement',
    ];

    protected $casts = [
        'self_submitted_at' => 'datetime',
        'manager_submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'overall_self_rating' => 'float',
        'overall_manager_rating' => 'float',
        'final_rating' => 'float',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplate::class, 'appraisal_template_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class, 'performance_appraisal_id');
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(AppraisalReviewer::class, 'appraisal_id');
    }

    public function selfResponses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class, 'performance_appraisal_id')
            ->where('respondent_type', AppraisalResponse::RESPONDENT_SELF);
    }

    public function managerResponses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class, 'performance_appraisal_id')
            ->where('respondent_type', AppraisalResponse::RESPONDENT_MANAGER);
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForCycle(Builder $query, int $cycleId): Builder
    {
        return $query->where('appraisal_cycle_id', $cycleId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ---------------------------------------------------------------------------
    // Business logic
    // ---------------------------------------------------------------------------

    /**
     * Calculate the weighted average rating for a given respondent type.
     *
     * Ratings from each section are weighted by the section's weight_percent.
     * Falls back to a simple average when weights are not configured (all zero).
     *
     * @param  string  $type  'self' or 'manager'
     */
    public function calculateOverallRating(string $type = 'self'): float
    {
        if ($this->template === null) {
            return 0.0;
        }

        $respondentType = $type === 'manager'
            ? AppraisalResponse::RESPONDENT_MANAGER
            : AppraisalResponse::RESPONDENT_SELF;

        /** @var \Illuminate\Database\Eloquent\Collection $responses */
        $responses = $this->responses()
            ->where('respondent_type', $respondentType)
            ->whereNotNull('rating')
            ->with('question.section')
            ->get();

        if ($responses->isEmpty()) {
            return 0.0;
        }

        // Group responses by section and compute per-section average
        $sectionAverages = [];
        $sectionWeights = [];

        foreach ($responses as $response) {
            $section = $response->question?->section;

            if ($section === null) {
                continue;
            }

            $sectionId = $section->id;

            if (!isset($sectionAverages[$sectionId])) {
                $sectionAverages[$sectionId] = [];
                $sectionWeights[$sectionId] = (float) $section->weight_percent;
            }

            $sectionAverages[$sectionId][] = (float) $response->rating;
        }

        if (empty($sectionAverages)) {
            return 0.0;
        }

        $totalWeight = array_sum($sectionWeights);
        $weightedSum = 0.0;
        $simpleRatings = [];

        foreach ($sectionAverages as $sectionId => $ratings) {
            $sectionAvg = array_sum($ratings) / count($ratings);
            $weight = $sectionWeights[$sectionId];
            $weightedSum += $sectionAvg * $weight;
            $simpleRatings[] = $sectionAvg;
        }

        // If weights are meaningful (sum > 0), use weighted average
        if ($totalWeight > 0.0) {
            return round($weightedSum / $totalWeight, 2);
        }

        // Fallback: simple average across sections
        return round(array_sum($simpleRatings) / count($simpleRatings), 2);
    }
}

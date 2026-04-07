<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalReviewer extends Model
{
    use BelongsToOrganization, HasUuid;

    // Reviewer types
    public const TYPE_SELF        = 'self';
    public const TYPE_PEER        = 'peer';
    public const TYPE_SUBORDINATE = 'subordinate';
    public const TYPE_MANAGER     = 'manager';
    public const TYPE_EXTERNAL    = 'external';

    public const TYPES = [
        self::TYPE_SELF,
        self::TYPE_PEER,
        self::TYPE_SUBORDINATE,
        self::TYPE_MANAGER,
        self::TYPE_EXTERNAL,
    ];

    // Review statuses
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED   = 'submitted';
    public const STATUS_DECLINED    = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_DECLINED,
    ];

    protected $table = 'appraisal_reviewers';

    protected $fillable = [
        'organization_id',
        'appraisal_id',
        'reviewer_id',
        'reviewer_type',
        'status',
        'submitted_at',
        'overall_rating',
        'strengths',
        'improvements',
        'comments',
        'is_anonymous',
        'due_date',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at'     => 'datetime',
            'reminder_sent_at' => 'datetime',
            'due_date'         => 'date',
            'overall_rating'   => 'decimal:2',
            'is_anonymous'     => 'boolean',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisal::class, 'appraisal_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AppraisalReviewerResponse::class, 'appraisal_reviewer_id');
    }

    // =========================================================================
    // Business logic
    // =========================================================================

    /**
     * Transition the reviewer record to submitted state.
     * Sets submitted_at to now and status to submitted.
     */
    public function submit(): self
    {
        $this->status       = self::STATUS_SUBMITTED;
        $this->submitted_at = now();
        $this->save();

        return $this;
    }

    /**
     * Transition the reviewer record to declined state.
     */
    public function decline(): self
    {
        $this->status = self::STATUS_DECLINED;
        $this->save();

        return $this;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canSubmit(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeForAppraisal($query, int $appraisalId)
    {
        return $query->where('appraisal_id', $appraisalId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('reviewer_type', $type);
    }
}

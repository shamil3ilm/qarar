<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalCycle extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasUuid;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SELF_REVIEW = 'self_review';
    public const STATUS_MANAGER_REVIEW = 'manager_review';
    public const STATUS_CALIBRATION = 'calibration';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_SELF_REVIEW,
        self::STATUS_MANAGER_REVIEW,
        self::STATUS_CALIBRATION,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'review_period_start',
        'review_period_end',
        'self_review_deadline',
        'manager_review_deadline',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'self_review_deadline' => 'date',
        'manager_review_deadline' => 'date',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function appraisals(): HasMany
    {
        return $this->hasMany(PerformanceAppraisal::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    // ---------------------------------------------------------------------------
    // Business logic helpers
    // ---------------------------------------------------------------------------

    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_DRAFT], true);
    }

    public function canSubmitSelfReview(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_SELF_REVIEW], true)
            && ($this->self_review_deadline === null || $this->self_review_deadline->isFuture() || $this->self_review_deadline->isToday());
    }

    public function canSubmitManagerReview(): bool
    {
        return in_array($this->status, [self::STATUS_SELF_REVIEW, self::STATUS_MANAGER_REVIEW], true)
            && ($this->manager_review_deadline === null || $this->manager_review_deadline->isFuture() || $this->manager_review_deadline->isToday());
    }
}

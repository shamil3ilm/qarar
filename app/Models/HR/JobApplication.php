<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobApplication extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid;

    // Status constants
    public const STATUS_APPLIED             = 'applied';
    public const STATUS_SCREENING           = 'screening';
    public const STATUS_SHORTLISTED         = 'shortlisted';
    public const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';
    public const STATUS_INTERVIEWED         = 'interviewed';
    public const STATUS_OFFER_EXTENDED      = 'offer_extended';
    public const STATUS_OFFER_ACCEPTED      = 'offer_accepted';
    public const STATUS_OFFER_DECLINED      = 'offer_declined';
    public const STATUS_HIRED               = 'hired';
    public const STATUS_REJECTED            = 'rejected';
    public const STATUS_WITHDRAWN           = 'withdrawn';

    /** Statuses from which further progression is allowed. */
    private const ADVANCEABLE_STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_SCREENING,
        self::STATUS_SHORTLISTED,
        self::STATUS_INTERVIEW_SCHEDULED,
        self::STATUS_INTERVIEWED,
        self::STATUS_OFFER_EXTENDED,
        self::STATUS_OFFER_ACCEPTED,
    ];

    protected $fillable = [
        'organization_id',
        'job_posting_id',
        'candidate_id',
        'status',
        'cover_letter',
        'expected_salary',
        'notice_period_days',
        'applied_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'expected_salary'    => 'decimal:2',
        'notice_period_days' => 'integer',
        'applied_at'         => 'datetime',
        'reviewed_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // -------------------------------------------------------------------------
    // Business logic helpers
    // -------------------------------------------------------------------------

    public function canAdvance(): bool
    {
        return in_array($this->status, self::ADVANCEABLE_STATUSES, true);
    }

    public function isHired(): bool
    {
        return $this->status === self::STATUS_HIRED;
    }
}

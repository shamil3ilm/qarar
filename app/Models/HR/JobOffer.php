<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JobOffer extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // Status constants
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'organization_id',
        'job_application_id',
        'candidate_id',
        'job_posting_id',
        'offered_salary',
        'currency_code',
        'joining_date',
        'offer_valid_until',
        'status',
        'terms',
        'sent_at',
        'responded_at',
        'decline_reason',
        'created_by',
    ];

    protected $casts = [
        'offered_salary'    => 'decimal:2',
        'joining_date'      => 'date',
        'offer_valid_until' => 'date',
        'sent_at'           => 'datetime',
        'responded_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    // -------------------------------------------------------------------------
    // Business logic helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->offer_valid_until !== null
            && $this->offer_valid_until->isPast()
            && $this->status === self::STATUS_SENT;
    }

    public function accept(int $userId): bool
    {
        if ($this->status !== self::STATUS_SENT) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return DB::transaction(function () use ($userId): bool {
            $this->update([
                'status'       => self::STATUS_ACCEPTED,
                'responded_at' => Carbon::now(),
            ]);

            $this->application()->update([
                'status'      => JobApplication::STATUS_OFFER_ACCEPTED,
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);

            return true;
        });
    }

    public function decline(string $reason, int $userId): bool
    {
        if ($this->status !== self::STATUS_SENT) {
            return false;
        }

        return DB::transaction(function () use ($reason, $userId): bool {
            $this->update([
                'status'         => self::STATUS_DECLINED,
                'decline_reason' => $reason,
                'responded_at'   => Carbon::now(),
            ]);

            $this->application()->update([
                'status'      => JobApplication::STATUS_OFFER_DECLINED,
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);

            return true;
        });
    }
}

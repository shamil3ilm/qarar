<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewSchedule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // Interview type constants
    public const TYPE_PHONE      = 'phone';
    public const TYPE_VIDEO      = 'video';
    public const TYPE_IN_PERSON  = 'in_person';
    public const TYPE_TECHNICAL  = 'technical';
    public const TYPE_PANEL      = 'panel';

    // Status constants
    public const STATUS_SCHEDULED  = 'scheduled';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_NO_SHOW    = 'no_show';

    protected $fillable = [
        'organization_id',
        'job_application_id',
        'interview_type',
        'scheduled_at',
        'duration_minutes',
        'location',
        'meeting_link',
        'interviewers',
        'status',
        'feedback',
        'rating',
        'recommendation',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'interviewers'     => 'array',
        'duration_minutes' => 'integer',
        'rating'           => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}

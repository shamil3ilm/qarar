<?php

declare(strict_types=1);

namespace App\Models\Campaign;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'trigger_event',
        'conditions',
        'target_segment_id',
        'actions',
        'status',
        'schedule_type',
        'delay_minutes',
        'scheduled_at',
        'start_date',
        'end_date',
        'max_sends_per_user',
        'created_by',
    ];

    protected $casts = [
        'conditions'   => 'array',
        'actions'      => 'array',
        'scheduled_at' => 'datetime',
        'start_date'   => 'date',
        'end_date'     => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(UserSegment::class, 'target_segment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRunnable(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $today = Carbon::today();

        if ($this->start_date !== null && $today->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date !== null && $today->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}

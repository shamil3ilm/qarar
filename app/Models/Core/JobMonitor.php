<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class JobMonitor extends Model
{
    use HasUuid;

    public const STATUS_QUEUED    = 'queued';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_RETRYING  = 'retrying';

    public const TRIGGERED_MANUAL    = 'manual';
    public const TRIGGERED_SCHEDULED = 'scheduled';
    public const TRIGGERED_EVENT     = 'event';
    public const TRIGGERED_SYSTEM    = 'system';

    protected $fillable = [
        'uuid',
        'organization_id',
        'job_class',
        'job_name',
        'queue_name',
        'status',
        'payload',
        'output',
        'error_message',
        'attempts',
        'max_attempts',
        'progress_percentage',
        'progress_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'next_retry_at',
        'run_duration_seconds',
        'triggered_by',
        'triggered_by_user_id',
        'tags',
    ];

    protected $casts = [
        'payload'              => 'array',
        'tags'                 => 'array',
        'queued_at'            => 'datetime',
        'started_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'failed_at'            => 'datetime',
        'next_retry_at'        => 'datetime',
        'attempts'             => 'integer',
        'max_attempts'         => 'integer',
        'progress_percentage'  => 'integer',
        'run_duration_seconds' => 'integer',
    ];

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
    }

    public function scopeByQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue_name', $queue);
    }

    // ==========================================
    // State Transitions
    // ==========================================

    public function markRunning(): void
    {
        $this->update([
            'status'     => self::STATUS_RUNNING,
            'started_at' => Carbon::now(),
            'attempts'   => $this->attempts + 1,
        ]);
    }

    public function markCompleted(string $output = ''): void
    {
        $completedAt = Carbon::now();
        $duration = $this->started_at
            ? (int) $this->started_at->diffInSeconds($completedAt)
            : null;

        $this->update([
            'status'               => self::STATUS_COMPLETED,
            'output'               => $output,
            'completed_at'         => $completedAt,
            'run_duration_seconds' => $duration,
            'progress_percentage'  => 100,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
            'failed_at'     => Carbon::now(),
        ]);
    }

    public function updateProgress(int $pct, string $message = ''): void
    {
        $this->update([
            'progress_percentage' => min(100, max(0, $pct)),
            'progress_message'    => $message,
        ]);
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(JobMonitorLog::class);
    }
}

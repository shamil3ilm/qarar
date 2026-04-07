<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class TrackedJob extends Model
{
    protected $fillable = [
        'job_class', 'job_key', 'organization_id', 'triggered_by_user_id',
        'payload', 'status', 'attempts', 'last_error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REPLAYED  = 'replayed';

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForOrganization($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    public function markRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'started_at' => now(), 'attempts' => $this->attempts + 1]);
    }

    public function markSucceeded(): void
    {
        $this->update(['status' => self::STATUS_SUCCEEDED, 'completed_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => self::STATUS_FAILED, 'last_error' => $error, 'completed_at' => now()]);
    }
}

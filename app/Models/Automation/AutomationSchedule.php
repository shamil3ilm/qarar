<?php

declare(strict_types=1);

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AutomationSchedule extends Model
{
    use HasFactory;
    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'rule_id',
        'scheduled_for',
        'executed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    // Relationships

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_for', '<=', now());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_for', '>', now())
            ->orderBy('scheduled_for');
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDue(): bool
    {
        return $this->isPending() && $this->scheduled_for->lte(now());
    }

    public function markAsRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'executed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'executed_at' => now(),
        ]);
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }
}

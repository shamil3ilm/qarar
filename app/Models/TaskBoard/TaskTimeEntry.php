<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskTimeEntry extends Model
{
    use HasFactory;
    protected $table = 'task_time_entries';

    protected $fillable = [
        'task_id',
        'user_id',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'is_billable',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'is_billable' => 'boolean',
        ];
    }

    // Relationships

    public function task(): BelongsTo
    {
        return $this->belongsTo(BoardTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeNonBillable($query)
    {
        return $query->where('is_billable', false);
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeStartedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    // Helpers

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function isBillable(): bool
    {
        return $this->is_billable;
    }

    public function stop(): void
    {
        $endedAt = now();
        $durationMinutes = (int) $this->started_at->diffInMinutes($endedAt);

        $this->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function getDurationFormatted(): string
    {
        $minutes = $this->duration_minutes ?? 0;
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }
}

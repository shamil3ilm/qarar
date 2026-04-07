<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskSprint extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'task_sprints';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'board_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'total_points',
        'completed_points',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_points' => 'integer',
            'completed_points' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskSprintItem::class, 'sprint_id');
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(
            BoardTask::class,
            TaskSprintItem::class,
            'sprint_id',
            'id',
            'id',
            'task_id'
        );
    }

    // Scopes

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForBoard($query, int $boardId)
    {
        return $query->where('board_id', $boardId);
    }

    public function scopeCurrent($query)
    {
        return $query->active()->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString());
    }

    // Helpers

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeStarted(): bool
    {
        return $this->isPlanned();
    }

    public function canBeCompleted(): bool
    {
        return $this->isActive();
    }

    public function getDurationInDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date);
    }

    public function getRemainingDays(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->end_date, false));
    }

    public function getElapsedDays(): int
    {
        if ($this->isPlanned()) {
            return 0;
        }

        $start = $this->started_at ?? $this->start_date;

        return (int) $start->diffInDays(now());
    }

    public function getCompletionPercentage(): float
    {
        if ($this->total_points === 0) {
            return 0;
        }

        return round(($this->completed_points / $this->total_points) * 100, 2);
    }

    public function getVelocity(): float
    {
        $elapsedDays = $this->getElapsedDays();

        if ($elapsedDays === 0) {
            return 0;
        }

        return round($this->completed_points / $elapsedDays, 2);
    }

    public function recalculatePoints(): void
    {
        $items = $this->items()->with('task')->get();

        $totalPoints = $items->sum('points');
        $completedPoints = $items->filter(function ($item) {
            return $item->task && $item->task->isCompleted();
        })->sum('points');

        $this->update([
            'total_points' => $totalPoints,
            'completed_points' => $completedPoints,
        ]);
    }
}

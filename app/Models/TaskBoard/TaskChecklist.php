<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskChecklist extends Model
{
    use HasFactory;
    protected $table = 'task_checklists';

    protected $fillable = [
        'task_id',
        'title',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    // Relationships

    public function task(): BelongsTo
    {
        return $this->belongsTo(BoardTask::class, 'task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    // Scopes

    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // Helpers

    public function getProgress(): array
    {
        $total = $this->items()->count();
        $completed = $this->items()->where('is_completed', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    public function isComplete(): bool
    {
        $total = $this->items()->count();

        if ($total === 0) {
            return true;
        }

        return $this->items()->where('is_completed', false)->count() === 0;
    }
}

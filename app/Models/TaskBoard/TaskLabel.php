<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskLabel extends Model
{
    use HasFactory;
    protected $table = 'task_labels';

    protected $fillable = [
        'board_id',
        'name',
        'color',
        'description',
    ];

    // Relationships

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(BoardTask::class, 'task_label_assignments', 'label_id', 'task_id')
            ->withTimestamps();
    }

    // Scopes

    public function scopeForBoard($query, int $boardId)
    {
        return $query->where('board_id', $boardId);
    }
}

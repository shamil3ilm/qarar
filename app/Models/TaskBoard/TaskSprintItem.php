<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSprintItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(TaskSprint::class, 'sprint_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(BoardTask::class, 'task_id');
    }
}
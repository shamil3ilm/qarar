<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardTask extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'tasks';

    protected $guarded = ['id'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reporter_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(TaskBoardColumn::class, 'column_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label_assignments', 'task_id', 'label_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BoardTaskComment::class, 'task_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class, 'task_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'task_id');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(TaskWatcher::class, 'task_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'done' || $this->status === 'completed';
    }
}
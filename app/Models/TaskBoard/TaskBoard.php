<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskBoard extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $table = 'task_boards';

    protected $guarded = ['id'];

    // Board types
    public const TYPE_KANBAN = 'kanban';
    public const TYPE_SCRUM = 'scrum';
    public const TYPE_SIMPLE = 'simple';

    // Visibility levels
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_TEAM = 'team';
    public const VISIBILITY_ORGANIZATION = 'organization';

    public function members(): HasMany
    {
        return $this->hasMany(TaskBoardMember::class, 'board_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(TaskBoardColumn::class, 'board_id')->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(TaskLabel::class, 'board_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeNotTemplates($query)
    {
        return $query->where('is_template', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('board_type', $type);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeWithVisibility($query, string $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(TaskSprint::class, 'board_id');
    }
}
<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskBoardColumn extends Model
{
    use HasFactory;
    protected $table = 'task_board_columns';

    protected $fillable = [
        'board_id',
        'name',
        'color',
        'position',
        'wip_limit',
        'is_done_column',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'wip_limit' => 'integer',
            'is_done_column' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    // Relationships

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(BoardTask::class, 'column_id')->orderBy('position');
    }

    // Scopes

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeDone($query)
    {
        return $query->where('is_done_column', true);
    }

    // Helpers

    public function isDoneColumn(): bool
    {
        return $this->is_done_column;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function hasWipLimit(): bool
    {
        return $this->wip_limit !== null;
    }

    public function isAtWipLimit(): bool
    {
        if (!$this->hasWipLimit()) {
            return false;
        }

        return $this->tasks()->count() >= $this->wip_limit;
    }

    public function getTaskCount(): int
    {
        return $this->tasks()->count();
    }
}

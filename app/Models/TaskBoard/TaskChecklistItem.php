<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskChecklistItem extends Model
{
    use HasFactory;
    protected $table = 'task_checklist_items';

    protected $fillable = [
        'checklist_id',
        'content',
        'is_completed',
        'completed_by',
        'completed_at',
        'assignee_id',
        'due_date',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }

    // Relationships

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'checklist_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // Scopes

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function scopeOverdue($query)
    {
        return $query->incomplete()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }

    // Helpers

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    public function toggle(): void
    {
        if ($this->is_completed) {
            $this->update([
                'is_completed' => false,
                'completed_by' => null,
                'completed_at' => null,
            ]);
        } else {
            $this->update([
                'is_completed' => true,
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        }
    }
}

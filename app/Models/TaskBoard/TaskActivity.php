<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskActivity extends Model
{
    use HasFactory;
    protected $table = 'task_activities';

    public const TYPE_CREATED = 'created';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_COMMENTED = 'commented';
    public const TYPE_ATTACHMENT_ADDED = 'attachment_added';
    public const TYPE_LABEL_ADDED = 'label_added';
    public const TYPE_LABEL_REMOVED = 'label_removed';
    public const TYPE_MOVED = 'moved';
    public const TYPE_PRIORITY_CHANGED = 'priority_changed';
    public const TYPE_DUE_DATE_CHANGED = 'due_date_changed';
    public const TYPE_CHECKLIST_ADDED = 'checklist_added';
    public const TYPE_TIME_LOGGED = 'time_logged';
    public const TYPE_DEPENDENCY_ADDED = 'dependency_added';
    public const TYPE_BLOCKED = 'blocked';
    public const TYPE_UNBLOCKED = 'unblocked';

    protected $fillable = [
        'task_id',
        'user_id',
        'activity_type',
        'field_name',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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

    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Helpers

    public static function log(
        int $taskId,
        string $activityType,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'activity_type' => $activityType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'metadata' => $metadata,
        ]);
    }
}

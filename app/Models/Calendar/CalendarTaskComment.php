<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CalendarTaskComment extends Model
{
    use HasFactory;
    protected $table = 'task_comments';

    protected $fillable = [
        'task_id',
        'user_id',
        'content',
    ];

    // Relationships

    public function task(): BelongsTo
    {
        return $this->belongsTo(CalendarTask::class, 'task_id');
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

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}

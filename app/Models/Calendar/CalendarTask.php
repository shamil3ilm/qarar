<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarTask extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(CalendarTask::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(CalendarTask::class, 'parent_task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(\App\Models\Core\Comment::class, 'commentable_id')
            ->where('commentable_type', self::class);
    }
}
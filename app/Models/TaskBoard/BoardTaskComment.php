<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardTaskComment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'task_comments';

    protected $guarded = ['id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(BoardTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
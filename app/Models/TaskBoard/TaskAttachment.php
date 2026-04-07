<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'task_attachments';

    protected $guarded = ['id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(BoardTask::class, 'task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
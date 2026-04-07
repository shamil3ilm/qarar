<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLabelAssignment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
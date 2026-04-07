<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTaskListOperation extends Model
{
    protected $table = 'pm_task_list_operations';

    protected $fillable = [
        'pm_task_list_id', 'operation_number', 'description',
        'work_center_id', 'planned_hours',
    ];

    protected $casts = ['planned_hours' => 'decimal:2'];

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(PmTaskList::class, 'pm_task_list_id');
    }
}

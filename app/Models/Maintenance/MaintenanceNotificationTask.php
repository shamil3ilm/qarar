<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceNotificationTask extends Model
{
    protected $table = 'maintenance_notification_tasks';

    /** @var list<string> */
    protected $fillable = [
        'notification_id',
        'task_number',
        'description',
        'details',
        'assigned_to',
        'planned_start_date',
        'planned_end_date',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date'   => 'date',
            'completed_at'       => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(MaintenanceNotification::class, 'notification_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

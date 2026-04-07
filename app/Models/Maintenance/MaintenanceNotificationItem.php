<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceNotificationItem extends Model
{
    protected $table = 'maintenance_notification_items';

    /** @var list<string> */
    protected $fillable = [
        'notification_id',
        'item_number',
        'short_text',
        'long_text',
        'damage_code',
        'cause_code',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(MaintenanceNotification::class, 'notification_id');
    }
}

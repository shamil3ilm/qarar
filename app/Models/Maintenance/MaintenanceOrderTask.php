<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrderTask extends Model
{
    protected $fillable = [
        'maintenance_order_id',
        'task_description',
        'is_safety_critical',
        'is_completed',
        'completed_at',
        'completed_by',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_safety_critical' => 'boolean',
            'is_completed'       => 'boolean',
            'completed_at'       => 'datetime',
            'sort_order'         => 'integer',
        ];
    }

    // Relations

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

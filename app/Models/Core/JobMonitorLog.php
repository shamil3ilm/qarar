<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMonitorLog extends Model
{
    public const UPDATED_AT = null;

    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_DEBUG   = 'debug';

    protected $fillable = [
        'job_monitor_id',
        'level',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public function jobMonitor(): BelongsTo
    {
        return $this->belongsTo(JobMonitor::class);
    }
}

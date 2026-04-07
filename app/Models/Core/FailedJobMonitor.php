<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `failed_jobs_monitor` table.
 *
 * This is a system-level table — it is NOT tenant-scoped and does NOT carry
 * an organization_id column. Do not add BelongsToOrganization or HasUuid here.
 */
class FailedJobMonitor extends Model
{
    protected $table = 'failed_jobs_monitor';

    protected $fillable = [
        'job_class',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];

    protected $casts = [
        'failed_at' => 'datetime',
    ];
}

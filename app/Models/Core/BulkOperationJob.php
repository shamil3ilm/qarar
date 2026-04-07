<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkOperationJob extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'operation_type', 'total_records', 'processed_records',
        'success_count', 'failure_count', 'status', 'payload', 'result_summary',
        'error_log', 'initiated_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'payload'        => 'array',
        'result_summary' => 'array',
        'error_log'      => 'array',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    public function initiatedBy(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by'); }
}

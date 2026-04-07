<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrcCcmException extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_ccm_exceptions';

    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_LOW      = 'low';

    public const STATUS_OPEN           = 'open';
    public const STATUS_ASSIGNED       = 'assigned';
    public const STATUS_INVESTIGATED   = 'investigated';
    public const STATUS_RESOLVED       = 'resolved';
    public const STATUS_FALSE_POSITIVE = 'false_positive';

    protected $fillable = [
        'organization_id',
        'monitor_id',
        'record_type',
        'record_id',
        'record_reference',
        'exception_details',
        'severity',
        'status',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'record_id'   => 'integer',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(GrcCcmMonitor::class, 'monitor_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

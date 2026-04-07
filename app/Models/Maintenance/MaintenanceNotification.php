<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceNotification extends Model
{
    use HasUuid;
    use SoftDeletes;
    use HasAuditTrail;

    protected $table = 'maintenance_notifications';

    // Notification type constants (SAP IW21-IW28)
    public const TYPE_ACTIVITY        = 'M1';
    public const TYPE_MALFUNCTION     = 'M2';
    public const TYPE_REQUEST         = 'M3';
    public const TYPE_SERVICE         = 'S1';
    public const TYPE_SERVICE_REQUEST = 'S4';

    // Status constants
    public const STATUS_OUTSTANDING    = 'OSNO';
    public const STATUS_NOT_IN_PROCESS = 'NOPR';
    public const STATUS_INITIAL        = 'INIT';
    public const STATUS_ORDER_ASSIGNED = 'ORAS';
    public const STATUS_NO_COMPLETION  = 'NOCO';
    public const STATUS_COMPLETED      = 'COMP';

    // Priority constants
    public const PRIORITY_VERY_HIGH = '1_very_high';
    public const PRIORITY_HIGH      = '2_high';
    public const PRIORITY_MEDIUM    = '3_medium';
    public const PRIORITY_LOW       = '4_low';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'notification_number',
        'notification_type',
        'short_text',
        'long_text',
        'equipment_id',
        'functional_location_code',
        'priority',
        'malfunction_start_date',
        'malfunction_end_date',
        'malfunction_duration_hours',
        'breakdown',
        'production_stop',
        'damage_code',
        'cause_code',
        'activity_code',
        'cause_text',
        'task_text',
        'status',
        'maintenance_order_id',
        'reported_by',
        'responsible_id',
        'completed_at',
        'completion_text',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'breakdown'                  => 'boolean',
            'production_stop'            => 'boolean',
            'malfunction_duration_hours' => 'decimal:2',
            'completed_at'               => 'datetime',
            'malfunction_start_date'     => 'date',
            'malfunction_end_date'       => 'date',
        ];
    }

    // Relations

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceNotificationItem::class, 'notification_id')
            ->orderBy('item_number');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceNotificationTask::class, 'notification_id')
            ->orderBy('task_number');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeOpen($query): mixed
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED]);
    }

    public function scopeBreakdown($query): mixed
    {
        return $query->where('breakdown', true);
    }
}

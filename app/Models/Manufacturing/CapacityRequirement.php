<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityRequirement extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_PLANNED      = 'planned';
    public const STATUS_SCHEDULED    = 'scheduled';
    public const STATUS_IN_PROGRESS  = 'in_progress';
    public const STATUS_COMPLETED    = 'completed';
    public const STATUS_CANCELLED    = 'cancelled';

    protected $fillable = [
        'organization_id',
        'work_order_id',
        'work_center_id',
        'operation_id',
        'required_hours',
        'scheduled_start',
        'scheduled_end',
        'status',
    ];

    protected $casts = [
        'required_hours'  => 'decimal:2',
        'scheduled_start' => 'datetime',
        'scheduled_end'   => 'datetime',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    // Scopes

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // Helpers

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PLANNED,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
        ], true);
    }
}

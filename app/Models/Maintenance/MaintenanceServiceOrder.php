<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceServiceOrder extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'maintenance_service_orders';

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_ISSUED      = 'issued';
    public const STATUS_CONFIRMED   = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'service_order_number',
        'maintenance_order_id',
        'equipment_id',
        'vendor_id',
        'status',
        'service_type',
        'description',
        'requested_date',
        'due_date',
        'completed_date',
        'estimated_cost',
        'actual_cost',
        'sla_response_hours',
        'sla_resolution_hours',
        'sla_response_due_at',
        'sla_resolution_due_at',
        'vendor_responded_at',
        'sla_breached',
        'purchase_order_id',
        'bill_id',
        'vendor_notes',
        'created_by',
    ];

    protected $casts = [
        'requested_date'        => 'date',
        'due_date'              => 'date',
        'completed_date'        => 'date',
        'sla_response_due_at'   => 'datetime',
        'sla_resolution_due_at' => 'datetime',
        'vendor_responded_at'   => 'datetime',
        'sla_breached'          => 'boolean',
        'estimated_cost'        => 'decimal:4',
        'actual_cost'           => 'decimal:4',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function checkSlaBreached(): bool
    {
        if (
            $this->sla_resolution_due_at !== null
            && now()->gt($this->sla_resolution_due_at)
            && $this->status !== self::STATUS_COMPLETED
        ) {
            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MaintenanceOrder extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use HasAuditTrail;
    use SoftDeletes;

    // Order type constants
    public const TYPE_PREVENTIVE  = 'preventive';
    public const TYPE_CORRECTIVE  = 'corrective';
    public const TYPE_EMERGENCY   = 'emergency';
    public const TYPE_INSPECTION  = 'inspection';

    public const ORDER_TYPES = [
        self::TYPE_PREVENTIVE,
        self::TYPE_CORRECTIVE,
        self::TYPE_EMERGENCY,
        self::TYPE_INSPECTION,
    ];

    // Priority constants
    public const PRIORITY_LOW      = 'low';
    public const PRIORITY_MEDIUM   = 'medium';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    // Status constants
    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD     = 'on_hold';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'order_number',
        'maintenance_plan_id',
        'equipment_id',
        'order_type',
        'priority',
        'status',
        'description',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'assigned_to',
        'estimated_cost',
        'actual_cost',
        'downtime_hours',
        'resolution_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end'   => 'datetime',
            'actual_start'    => 'datetime',
            'actual_end'      => 'datetime',
            'estimated_cost'  => 'decimal:2',
            'actual_cost'     => 'decimal:2',
            'downtime_hours'  => 'decimal:2',
        ];
    }

    // Relations

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceOrderTask::class, 'maintenance_order_id')
            ->orderBy('sort_order');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenanceOrderPart::class, 'maintenance_order_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // State transitions

    /**
     * Transition the order to in_progress status.
     */
    public function start(int $userId): self
    {
        if (!in_array($this->status, [self::STATUS_OPEN, self::STATUS_ON_HOLD], true)) {
            throw new \InvalidArgumentException(
                "Cannot start a maintenance order with status '{$this->status}'."
            );
        }

        $this->update([
            'status'       => self::STATUS_IN_PROGRESS,
            'actual_start' => $this->actual_start ?? now(),
        ]);

        // Mark equipment as under maintenance
        $this->equipment->update(['status' => Equipment::STATUS_UNDER_MAINTENANCE]);

        return $this->fresh();
    }

    /**
     * Complete the order and update equipment maintenance dates.
     */
    public function complete(array $data, int $userId): self
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException(
                "Only in-progress orders can be completed. Current status: '{$this->status}'."
            );
        }

        DB::transaction(function () use ($data): void {
            $completedAt = now();

            $this->update([
                'status'           => self::STATUS_COMPLETED,
                'actual_end'       => $completedAt,
                'resolution_notes' => $data['resolution_notes'] ?? null,
                'actual_cost'      => $data['actual_cost'] ?? $this->actual_cost,
                'downtime_hours'   => $data['downtime_hours'] ?? $this->downtime_hours,
            ]);

            // Update equipment last/next maintenance dates
            $equipment = $this->equipment;
            $updateData = [
                'status'                => Equipment::STATUS_ACTIVE,
                'last_maintenance_date' => $completedAt->toDateString(),
            ];

            // If there is a linked plan, compute the next due date
            if ($this->maintenance_plan_id !== null) {
                $plan = $this->plan ?? MaintenancePlan::find($this->maintenance_plan_id);
                if ($plan !== null) {
                    $next = $plan->calculateNextDueDate(new \DateTime($completedAt->toDateString()));
                    $updateData['next_maintenance_date'] = $next->format('Y-m-d');
                }
            }

            $equipment->update($updateData);
        });

        return $this->fresh();
    }

    /**
     * Cancel the order.
     */
    public function cancel(int $userId): self
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException(
                "Cannot cancel a maintenance order with status '{$this->status}'."
            );
        }

        $this->update(['status' => self::STATUS_CANCELLED]);

        // Restore equipment status to active if it was put under maintenance by this order
        if ($this->equipment->status === Equipment::STATUS_UNDER_MAINTENANCE) {
            $this->equipment->update(['status' => Equipment::STATUS_ACTIVE]);
        }

        return $this->fresh();
    }

    // Static helpers

    /**
     * Generate a sequential order number: MO-YYYY-000001
     */
    public static function generateOrderNumber(int $orgId): string
    {
        $year  = now()->format('Y');
        $prefix = "MO-{$year}-";

        $last = static::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('order_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('order_number');

        if ($last === null) {
            $seq = 1;
        } else {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeForEquipment($query, int $equipmentId)
    {
        return $query->where('equipment_id', $equipmentId);
    }
}

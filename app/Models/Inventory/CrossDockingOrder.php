<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrossDockingOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const INBOUND_PURCHASE_ORDER = 'purchase_order';
    public const INBOUND_TRANSFER_ORDER = 'transfer_order';
    public const INBOUND_RETURN = 'return';

    public const OUTBOUND_SALES_ORDER = 'sales_order';
    public const OUTBOUND_TRANSFER_ORDER = 'transfer_order';
    public const OUTBOUND_DELIVERY = 'delivery';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'inbound_source_type',
        'inbound_source_id',
        'outbound_dest_type',
        'outbound_dest_id',
        'planned_date',
        'actual_date',
        'status',
        'dock_door_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_date' => 'datetime',
            'actual_date'  => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CrossDockingOrderLine::class);
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}

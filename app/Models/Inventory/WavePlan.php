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

class WavePlan extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // Wave type constants
    public const TYPE_OUTBOUND      = 'outbound';
    public const TYPE_REPLENISHMENT = 'replenishment';
    public const TYPE_RETURNS       = 'returns';

    // Status constants
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_RELEASED  = 'released';
    public const STATUS_PICKING   = 'picking';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'wave_number',
        'wave_type',
        'status',
        'planned_date',
        'total_orders',
        'total_lines',
        'total_units',
        'released_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_date'  => 'date',
            'total_orders'  => 'integer',
            'total_lines'   => 'integer',
            'total_units'   => 'decimal:4',
            'released_at'   => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function waveOrders(): HasMany
    {
        return $this->hasMany(WavePlanOrder::class);
    }

    public function pickingLists(): HasMany
    {
        return $this->hasMany(PickingList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function release(int $userId): self
    {
        $this->status      = self::STATUS_RELEASED;
        $this->released_at = now();
        $this->save();

        return $this;
    }

    public function complete(int $userId): self
    {
        $this->status       = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function getCompletionPercent(): float
    {
        if ($this->total_lines === 0) {
            return 0.0;
        }

        $pickedLines = $this->pickingLists()
            ->withCount(['lines as completed_lines_count' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get()
            ->sum('completed_lines_count');

        return round(($pickedLines / $this->total_lines) * 100, 2);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RELEASED, self::STATUS_PICKING]);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}

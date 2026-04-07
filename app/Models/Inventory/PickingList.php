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

class PickingList extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // Status constants
    public const STATUS_PENDING     = 'pending';
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_PARTIAL     = 'partial';
    public const STATUS_CANCELLED   = 'cancelled';

    // Picking type constants
    public const TYPE_SINGLE_ORDER = 'single_order';
    public const TYPE_MULTI_ORDER  = 'multi_order';
    public const TYPE_ZONE         = 'zone';
    public const TYPE_CLUSTER      = 'cluster';

    protected $fillable = [
        'organization_id',
        'wave_plan_id',
        'warehouse_id',
        'list_number',
        'picker_id',
        'status',
        'picking_type',
        'total_lines',
        'picked_lines',
        'assigned_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_lines'  => 'integer',
            'picked_lines' => 'integer',
            'assigned_at'  => 'datetime',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(WavePlan::class, 'wave_plan_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickingListLine::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assign(int $pickerId, int $userId): self
    {
        $this->picker_id   = $pickerId;
        $this->status      = self::STATUS_ASSIGNED;
        $this->assigned_at = now();
        $this->save();

        return $this;
    }

    public function start(int $userId): self
    {
        $this->status     = self::STATUS_IN_PROGRESS;
        $this->started_at = now();
        $this->save();

        return $this;
    }

    public function complete(int $userId): self
    {
        $pendingLines = $this->lines()->whereIn('status', ['pending', 'partial'])->count();

        $this->status       = $pendingLines > 0 ? self::STATUS_PARTIAL : self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function getCompletionPercent(): float
    {
        if ($this->total_lines === 0) {
            return 0.0;
        }

        return round(($this->picked_lines / $this->total_lines) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL], true);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', self::STATUS_ASSIGNED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeForPicker($query, int $pickerId)
    {
        return $query->where('picker_id', $pickerId);
    }
}

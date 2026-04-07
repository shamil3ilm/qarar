<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrpRun extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'run_date',
        'planning_horizon_days',
        'status',
        'total_products_analyzed',
        'total_planned_orders',
        'error_message',
        'run_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date'                 => 'datetime',
            'planning_horizon_days'    => 'integer',
            'total_products_analyzed'  => 'integer',
            'total_planned_orders'     => 'integer',
            'completed_at'             => 'datetime',
        ];
    }

    public function demandItems(): HasMany
    {
        return $this->hasMany(MrpDemandItem::class);
    }

    public function plannedOrders(): HasMany
    {
        return $this->hasMany(MrpPlannedOrder::class);
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EwmTransferOrder extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'ewm_transfer_orders';

    public const MOVEMENT_GOODS_RECEIPT      = 'goods_receipt';
    public const MOVEMENT_GOODS_ISSUE        = 'goods_issue';
    public const MOVEMENT_INTERNAL_MOVE      = 'internal_move';
    public const MOVEMENT_REPLENISHMENT      = 'replenishment';
    public const MOVEMENT_STOCK_TRANSFER     = 'stock_transfer';
    public const MOVEMENT_PHYSICAL_INVENTORY = 'physical_inventory';

    public const STATUS_CREATED     = 'created';
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CONFIRMED   = 'confirmed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_qty'           => 'float',
            'confirmed_qty'           => 'float',
            'actual_duration_minutes' => 'float',
            'assigned_at'             => 'datetime',
            'started_at'              => 'datetime',
            'confirmed_at'            => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(EwmBin::class, 'source_bin_id');
    }

    public function destBin(): BelongsTo
    {
        return $this->belongsTo(EwmBin::class, 'dest_bin_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function laborTasks(): HasMany
    {
        return $this->hasMany(EwmLaborTask::class, 'transfer_order_id');
    }
}

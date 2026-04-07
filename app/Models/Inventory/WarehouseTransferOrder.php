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

class WarehouseTransferOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const MOVEMENT_GOODS_RECEIPT    = 'goods_receipt';
    public const MOVEMENT_GOODS_ISSUE      = 'goods_issue';
    public const MOVEMENT_INTERNAL         = 'internal_transfer';
    public const MOVEMENT_REPLENISHMENT    = 'replenishment';

    public const STATUS_CREATED     = 'created';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CONFIRMED   = 'confirmed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'to_number',
        'warehouse_id',
        'movement_type',
        'source_document_type',
        'source_document_ref',
        'source_location_id',
        'dest_location_id',
        'status',
        'assigned_to',
        'confirmed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }

    public function destLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'dest_location_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferOrderItem::class, 'transfer_order_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_IN_PROGRESS], true);
    }

    /**
     * Generate TO number: TO-YYYY-000001 scoped to organization.
     */
    public static function generateToNumber(int $orgId): string
    {
        $year   = now()->format('Y');
        $prefix = "TO-{$year}-";

        $last = static::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('to_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('to_number');

        $seq = $last === null ? 1 : ((int) substr($last, strlen($prefix)) + 1);

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeCreated($query)
    {
        return $query->where('status', self::STATUS_CREATED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }
}

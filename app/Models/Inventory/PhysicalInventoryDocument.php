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

class PhysicalInventoryDocument extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_CREATED = 'created';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COUNTED = 'counted';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'document_number',
        'warehouse_id',
        'count_date',
        'inventory_type',
        'status',
        'assigned_to',
        'counted_at',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'counted_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PhysicalInventoryLine::class, 'document_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_IN_PROGRESS], true);
    }

    public function canEnterCounts(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_IN_PROGRESS], true);
    }

    public function canPost(): bool
    {
        return in_array($this->status, [self::STATUS_COUNTED, self::STATUS_IN_PROGRESS], true)
            && $this->lines()->where('adjustment_status', 'pending')->exists();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COUNTED,
        ], true);
    }

    public function getTotalDifferenceValue(): float
    {
        return (float) $this->lines()->whereNotNull('difference_value')->sum('difference_value');
    }

    public function getLinesWithDifferences(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->lines()->whereNotNull('difference_quantity')
            ->where('difference_quantity', '!=', 0)
            ->get();
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

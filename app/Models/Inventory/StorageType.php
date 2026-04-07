<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageType extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public const CLASS_BULK          = 'bulk';
    public const CLASS_RACK          = 'rack';
    public const CLASS_FLOOR         = 'floor';
    public const CLASS_REFRIGERATED  = 'refrigerated';
    public const CLASS_HAZMAT        = 'hazmat';
    public const CLASS_HIGH_SECURITY = 'high_security';
    public const CLASS_QUARANTINE    = 'quarantine';

    public const CAP_NO_CHECK      = 'no_check';
    public const CAP_TOTAL_WEIGHT  = 'total_weight';
    public const CAP_TOTAL_QTY     = 'total_qty';
    public const CAP_OCCUPIED_BINS = 'occupied_bins';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'max_weight'                  => 'decimal:2',
            'max_quantity'                => 'decimal:4',
            'total_bins'                  => 'integer',
            'current_utilization_percent' => 'decimal:2',
            'is_active'                   => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function determinationRules(): HasMany
    {
        return $this->hasMany(StorageTypeDeterminationRule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Check whether this storage type still has available capacity.
     * Falls back to true when capacity management is set to no_check.
     */
    public function hasCapacity(): bool
    {
        return match ($this->capacity_management) {
            self::CAP_TOTAL_WEIGHT,
            self::CAP_TOTAL_QTY,
            self::CAP_OCCUPIED_BINS => (float) $this->current_utilization_percent < 100.0,
            default                 => true,
        };
    }
}

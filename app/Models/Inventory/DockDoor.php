<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DockDoor extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_INBOUND = 'inbound';
    public const TYPE_OUTBOUND = 'outbound';
    public const TYPE_COMBINED = 'combined';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'door_code',
        'door_type',
        'yard_zone_id',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function yardZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(TruckAppointment::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->is_active;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)->where('is_active', true);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeInbound($query)
    {
        return $query->whereIn('door_type', [self::TYPE_INBOUND, self::TYPE_COMBINED]);
    }

    public function scopeOutbound($query)
    {
        return $query->whereIn('door_type', [self::TYPE_OUTBOUND, self::TYPE_COMBINED]);
    }
}

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

class YardZone extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_STAGING = 'staging';
    public const TYPE_PARKING = 'parking';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_DOCK = 'dock';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'zone_code',
        'name',
        'zone_type',
        'capacity_vehicles',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'capacity_vehicles' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function dockDoors(): HasMany
    {
        return $this->hasMany(DockDoor::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(YardMovement::class, 'to_zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanSupplyArea extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'warehouse_id',
        'location_id',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function controlCycles(): HasMany
    {
        return $this->hasMany(KanbanControlCycle::class, 'supply_area_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

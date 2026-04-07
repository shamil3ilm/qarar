<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingRoute extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'route_code',
        'route_name',
        'departure_zone_id',
        'destination_zone_id',
        'transportation_mode',
        'transit_days',
        'carrier',
        'freight_cost',
        'is_active',
    ];

    protected $casts = [
        'transit_days' => 'integer',
        'freight_cost' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function departureZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'departure_zone_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'destination_zone_id');
    }

    public function determinations(): HasMany
    {
        return $this->hasMany(ShippingRouteDetermination::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

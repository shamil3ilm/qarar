<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryMode extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'code', 'type', 'description', 'icon',
        'pricing_type', 'flat_rate', 'pricing_rules', 'min_delivery_days',
        'max_delivery_days', 'delivery_time_label', 'free_shipping_min',
        'max_weight_kg', 'max_value', 'supported_zones', 'excluded_products',
        'tracking_enabled', 'carrier_provider', 'carrier_config', 'available_days',
        'cutoff_time', 'requires_address', 'is_active', 'display_order',
    ];

    protected $casts = [
        'flat_rate' => 'decimal:2',
        'free_shipping_min' => 'decimal:2',
        'max_weight_kg' => 'decimal:2',
        'max_value' => 'decimal:2',
        'pricing_rules' => 'array',
        'supported_zones' => 'array',
        'excluded_products' => 'array',
        'carrier_config' => 'array',
        'available_days' => 'array',
        'tracking_enabled' => 'boolean',
        'requires_address' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function zoneRates(): HasMany
    {
        return $this->hasMany(DeliveryZoneRate::class, 'delivery_mode_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'delivery_mode_id');
    }
}

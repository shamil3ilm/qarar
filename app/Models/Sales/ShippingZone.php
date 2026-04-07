<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingZone extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'zone_code',
        'zone_name',
        'country_codes',
        'postal_code_pattern',
        'is_active',
    ];

    protected $casts = [
        'country_codes' => 'array',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function departureRoutes(): HasMany
    {
        return $this->hasMany(ShippingRoute::class, 'departure_zone_id');
    }

    public function destinationRoutes(): HasMany
    {
        return $this->hasMany(ShippingRoute::class, 'destination_zone_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function matchesAddress(string $country, ?string $postalCode): bool
    {
        $countryCodes = $this->country_codes ?? [];

        if (!empty($countryCodes) && !in_array(strtoupper($country), array_map('strtoupper', $countryCodes), true)) {
            return false;
        }

        if ($postalCode !== null && !empty($this->postal_code_pattern)) {
            if (!preg_match('/' . $this->postal_code_pattern . '/', $postalCode)) {
                return false;
            }
        }

        return true;
    }
}

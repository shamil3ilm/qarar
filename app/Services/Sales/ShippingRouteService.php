<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\Sales\ShippingRoute;
use App\Models\Sales\ShippingRouteDetermination;
use App\Models\Sales\ShippingZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShippingRouteService
{
    public function listZones(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ShippingZone::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function createZone(array $data): ShippingZone
    {
        return ShippingZone::create($data);
    }

    public function updateZone(ShippingZone $zone, array $data): ShippingZone
    {
        $zone->update($data);
        return $zone->fresh();
    }

    public function listRoutes(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ShippingRoute::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (!empty($filters['transportation_mode'])) {
            $query->where('transportation_mode', $filters['transportation_mode']);
        }
        if (!empty($filters['departure_zone_id'])) {
            $query->where('departure_zone_id', $filters['departure_zone_id']);
        }
        if (!empty($filters['destination_zone_id'])) {
            $query->where('destination_zone_id', $filters['destination_zone_id']);
        }

        return $query->with(['departureZone', 'destinationZone'])->latest()->paginate($perPage);
    }

    public function createRoute(array $data): ShippingRoute
    {
        return ShippingRoute::create($data);
    }

    public function updateRoute(ShippingRoute $route, array $data): ShippingRoute
    {
        $route->update($data);
        return $route->fresh(['departureZone', 'destinationZone']);
    }

    /**
     * Determine the best shipping route based on destination address and warehouse country.
     *
     * @param  string      $destinationCountry  ISO-2 country code of the destination
     * @param  string|null $postalCode          Destination postal code (optional)
     * @param  string|null $warehouseCountry    ISO-2 country code of the departure warehouse (default 'SA')
     * @param  string      $preference          'cheapest' or 'fastest'
     */
    public function determineRoute(
        string $destinationCountry,
        ?string $postalCode = null,
        ?string $warehouseCountry = 'SA',
        string $preference = 'cheapest'
    ): ?ShippingRoute {
        // Find matching destination zone
        $destinationZone = $this->findMatchingZone($destinationCountry, $postalCode);
        if ($destinationZone === null) {
            return null;
        }

        // Find matching departure zone
        $departureZone = $this->findMatchingZone($warehouseCountry ?? 'SA', null);
        if ($departureZone === null) {
            return null;
        }

        // Find active routes between the two zones
        $routeQuery = ShippingRoute::active()
            ->where('departure_zone_id', $departureZone->id)
            ->where('destination_zone_id', $destinationZone->id);

        if ($preference === 'fastest') {
            return $routeQuery->orderBy('transit_days')->first();
        }

        return $routeQuery->orderBy('freight_cost')->first();
    }

    public function determineForOrder(int $salesOrderId): ShippingRouteDetermination
    {
        $order = SalesOrder::findOrFail($salesOrderId);

        $shippingAddress = is_array($order->shipping_address) ? $order->shipping_address : [];
        $country = $shippingAddress['country'] ?? $shippingAddress['country_code'] ?? 'SA';
        $postalCode = $shippingAddress['postal_code'] ?? $shippingAddress['zip'] ?? null;

        $route = $this->determineRoute($country, $postalCode);

        $destinationZone = $route
            ? $route->destinationZone
            : $this->findMatchingZone($country, $postalCode);

        $departureZone = $route ? $route->departureZone : null;

        return DB::transaction(function () use ($order, $route, $departureZone, $destinationZone): ShippingRouteDetermination {
            return ShippingRouteDetermination::create([
                'organization_id' => $order->organization_id,
                'sales_order_id' => $order->id,
                'shipping_route_id' => $route?->id,
                'departure_zone_id' => $departureZone?->id,
                'destination_zone_id' => $destinationZone?->id,
                'determined_at' => now(),
            ]);
        });
    }

    private function findMatchingZone(string $country, ?string $postalCode): ?ShippingZone
    {
        $zones = ShippingZone::active()->get();

        foreach ($zones as $zone) {
            if ($zone->matchesAddress($country, $postalCode)) {
                return $zone;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\ShippingRoute;
use App\Models\Sales\ShippingZone;
use App\Services\Sales\ShippingRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingRouteController extends Controller
{
    public function __construct(
        private ShippingRouteService $shippingRouteService,
    ) {}

    // -------------------------------------------------------------------------
    // Shipping Zones
    // -------------------------------------------------------------------------

    public function zoneIndex(Request $request): JsonResponse
    {
        $zones = $this->shippingRouteService->listZones(
            $request->only(['is_active']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($zones);
    }

    public function zoneStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_code' => 'required|string|max:20',
            'zone_name' => 'required|string|max:100',
            'country_codes' => 'nullable|array',
            'country_codes.*' => 'string|size:2',
            'postal_code_pattern' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $zone = $this->shippingRouteService->createZone(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($zone);
    }

    public function zoneShow(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);

        return $this->success($zone);
    }

    public function zoneUpdate(Request $request, int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'zone_code' => 'nullable|string|max:20',
            'zone_name' => 'nullable|string|max:100',
            'country_codes' => 'nullable|array',
            'country_codes.*' => 'string|size:2',
            'postal_code_pattern' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->shippingRouteService->updateZone($zone, $validator->validated());

        return $this->success($updated);
    }

    public function zoneDestroy(int $id): JsonResponse
    {
        ShippingZone::findOrFail($id)->delete();

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Shipping Routes
    // -------------------------------------------------------------------------

    public function routeIndex(Request $request): JsonResponse
    {
        $routes = $this->shippingRouteService->listRoutes(
            $request->only(['is_active', 'transportation_mode', 'departure_zone_id', 'destination_zone_id']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($routes);
    }

    public function routeStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_code' => 'required|string|max:30',
            'route_name' => 'required|string|max:100',
            'departure_zone_id' => 'required|exists:shipping_zones,id',
            'destination_zone_id' => 'required|exists:shipping_zones,id',
            'transportation_mode' => 'required|in:road,air,sea,rail,courier',
            'transit_days' => 'nullable|integer|min:1',
            'carrier' => 'nullable|string|max:100',
            'freight_cost' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $route = $this->shippingRouteService->createRoute(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($route->load(['departureZone', 'destinationZone']));
    }

    public function routeShow(int $id): JsonResponse
    {
        $route = ShippingRoute::with(['departureZone', 'destinationZone'])->findOrFail($id);

        return $this->success($route);
    }

    public function routeUpdate(Request $request, int $id): JsonResponse
    {
        $route = ShippingRoute::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'route_code' => 'nullable|string|max:30',
            'route_name' => 'nullable|string|max:100',
            'departure_zone_id' => 'nullable|exists:shipping_zones,id',
            'destination_zone_id' => 'nullable|exists:shipping_zones,id',
            'transportation_mode' => 'nullable|in:road,air,sea,rail,courier',
            'transit_days' => 'nullable|integer|min:1',
            'carrier' => 'nullable|string|max:100',
            'freight_cost' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->shippingRouteService->updateRoute($route, $validator->validated());

        return $this->success($updated);
    }

    public function routeDestroy(int $id): JsonResponse
    {
        ShippingRoute::findOrFail($id)->delete();

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Route Determination
    // -------------------------------------------------------------------------

    public function determineRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'destination_country' => 'required|string|size:2',
            'postal_code' => 'nullable|string|max:20',
            'warehouse_country' => 'nullable|string|size:2',
            'preference' => 'nullable|in:cheapest,fastest',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $route = $this->shippingRouteService->determineRoute(
            $request->input('destination_country'),
            $request->input('postal_code'),
            $request->input('warehouse_country', 'SA'),
            $request->input('preference', 'cheapest')
        );

        if ($route === null) {
            return $this->error('No matching shipping route found.', 'NOT_FOUND', 404);
        }

        return $this->success($route->load(['departureZone', 'destinationZone']));
    }

    public function determineForOrder(int $orderId): JsonResponse
    {
        $determination = $this->shippingRouteService->determineForOrder($orderId);

        return $this->success(
            $determination->load(['shippingRoute.departureZone', 'shippingRoute.destinationZone', 'departureZone', 'destinationZone']),
            'Route determined successfully.'
        );
    }
}

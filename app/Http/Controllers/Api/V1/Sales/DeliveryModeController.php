<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\DeliveryMode;
use App\Services\Sales\PaymentDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryModeController extends Controller
{
    public function __construct(private PaymentDeliveryService $service) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->getDeliveryModes(auth()->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30',
            'type' => 'required|string|in:pickup,standard,express,same_day,next_day,freight,digital,custom,shipping',
            'description' => 'nullable|string',
            'pricing_type' => 'nullable|string|in:free,flat,flat_rate,weight_based,value_based,distance_based,custom',
            'flat_rate' => 'nullable|numeric|min:0',
            'min_delivery_days' => 'nullable|integer|min:0',
            'max_delivery_days' => 'nullable|integer|min:0',
            'delivery_time_label' => 'nullable|string',
            'free_shipping_min' => 'nullable|numeric|min:0',
            'tracking_enabled' => 'nullable|boolean',
            'requires_address' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'pricing_rules' => 'nullable|array',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $mode = $this->service->createDeliveryMode($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($mode);
    }

    public function show(DeliveryMode $mode): JsonResponse
    {
        return $this->success($mode->load('zoneRates'));
    }

    public function update(Request $request, DeliveryMode $mode): JsonResponse
    {
        $mode->update($request->all());
        return $this->success($mode->fresh());
    }

    public function destroy(DeliveryMode $mode): JsonResponse
    {
        $mode->delete();
        return $this->success(['message' => 'Delivery mode deleted']);
    }

    public function calculateShipping(Request $request): JsonResponse
    {
        $result = $this->service->calculateShippingCost(
            $request->input('delivery_mode_id'),
            $request->input('zone_id'),
            (float) $request->input('total_weight_kg', $request->input('weight', 0)),
            (float) $request->input('order_total', $request->input('order_value', 0))
        );

        // Map 'cost' to 'shipping_cost' for API consumers
        if (isset($result['cost']) && !isset($result['shipping_cost'])) {
            $result['shipping_cost'] = $result['cost'];
        }

        return $this->success($result);
    }
}

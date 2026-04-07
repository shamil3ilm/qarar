<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Shipment;
use App\Services\Sales\PaymentDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(private PaymentDeliveryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $shipments = Shipment::with('deliveryMode', 'contact')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($shipments);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'delivery_mode_id' => 'required|integer|exists:delivery_modes,id',
            'contact_id' => 'required|integer|exists:contacts,id',
            'source_type' => 'required|string|max:100',
            'shipping_address' => 'required|array',
            'ship_date' => 'nullable|date',
            'estimated_delivery' => 'nullable|date',
            'total_weight_kg' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|max:3',
            'carrier' => 'nullable|string',
            'tracking_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $shipment = $this->service->createShipment($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($shipment);
    }

    public function show(Shipment $shipment): JsonResponse
    {
        return $this->success($shipment->load('items.product', 'trackingEvents', 'deliveryMode'));
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment->update($request->all());
        return $this->success($shipment->fresh());
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $shipment->delete();
        return $this->success(['message' => 'Shipment deleted']);
    }

    public function updateStatus(Request $request, Shipment $shipment): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,picked,packed,shipped,in_transit,out_for_delivery,delivered,failed,returned',
            'notes' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        try {
            $updated = $this->service->updateShipmentStatus($shipment, $request->input('status'), $request->all());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($updated);
    }

    public function tracking(Shipment $shipment): JsonResponse
    {
        return $this->success($shipment->trackingEvents()->orderBy('event_at')->get());
    }
}

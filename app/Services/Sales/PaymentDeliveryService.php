<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\DeliveryMode;
use App\Models\Sales\DeliveryZoneRate;
use App\Models\Sales\PaymentMode;
use App\Models\Sales\Shipment;
use App\Models\Sales\ShipmentTrackingEvent;
use Illuminate\Support\Facades\DB;

class PaymentDeliveryService
{
    public function getPaymentModes(int $organizationId): mixed
    {
        return PaymentMode::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    public function createPaymentMode(array $data): PaymentMode
    {
        return PaymentMode::create($data);
    }

    public function getDeliveryModes(int $organizationId): mixed
    {
        return DeliveryMode::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    public function createDeliveryMode(array $data): DeliveryMode
    {
        return DeliveryMode::create($data);
    }

    public function calculateShippingCost(int $deliveryModeId, ?int $zoneId, float $weight, float $orderValue): array
    {
        $mode = DeliveryMode::findOrFail($deliveryModeId);

        if ($mode->free_shipping_min && $orderValue >= $mode->free_shipping_min) {
            return ['cost' => 0, 'free_shipping' => true, 'delivery_time' => $mode->delivery_time_label];
        }

        $cost = match ($mode->pricing_type) {
            'free' => 0,
            'flat', 'flat_rate' => (float) $mode->flat_rate,
            'weight_based' => $this->calculateWeightBasedCost($deliveryModeId, $zoneId, $weight),
            default => (float) $mode->flat_rate,
        };

        return [
            'cost' => round($cost, 2),
            'free_shipping' => false,
            'delivery_time' => $mode->delivery_time_label,
        ];
    }

    private function calculateWeightBasedCost(int $deliveryModeId, ?int $zoneId, float $weight): float
    {
        if (!$zoneId) {
            return 0;
        }

        $rate = DeliveryZoneRate::where('delivery_mode_id', $deliveryModeId)
            ->where('zone_id', $zoneId)
            ->where('min_weight', '<=', $weight)
            ->where(function ($q) use ($weight) {
                $q->whereNull('max_weight')->orWhere('max_weight', '>=', $weight);
            })
            ->first();

        return $rate ? (float) $rate->rate : 0;
    }

    public function createShipment(array $data): Shipment
    {
        return DB::transaction(function () use ($data) {
            $shipment = Shipment::create($data);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $shipment->items()->create($item);
                }
            }

            return $shipment->load('items');
        });
    }

    public function updateShipmentStatus(Shipment $shipment, string $status, array $eventData = []): Shipment
    {
        return DB::transaction(function () use ($shipment, $status, $eventData) {
            $shipment->update(['status' => $status]);

            ShipmentTrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'description' => $eventData['description'] ?? "Status changed to {$status}",
                'location' => $eventData['location'] ?? null,
                'event_at' => now(),
            ]);

            if ($status === 'delivered') {
                $shipment->update(['actual_delivery' => now()]);
            }

            return $shipment->fresh();
        });
    }
}

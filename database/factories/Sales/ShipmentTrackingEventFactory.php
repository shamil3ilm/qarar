<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ShipmentTrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentTrackingEventFactory extends Factory
{
    protected $model = ShipmentTrackingEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id' => fake()->randomNumber(3, true),
            'status' => fake()->randomElement([
                'label_created',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'exception',
                'returned',
            ]),
            'description' => fake()->sentence(),
            'location' => fake()->city() . ', ' . fake()->randomElement(['SA', 'AE', 'IN']),
            'event_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'raw_data' => null,
        ];
    }

    public function labelCreated(): static
    {
        return $this->state(fn () => [
            'status' => 'label_created',
            'description' => 'Shipping label created',
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state(fn () => [
            'status' => 'picked_up',
            'description' => 'Package picked up by carrier',
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(fn () => [
            'status' => 'in_transit',
            'description' => 'Package in transit',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'description' => 'Package delivered successfully',
        ]);
    }

    public function exception(): static
    {
        return $this->state(fn () => [
            'status' => 'exception',
            'description' => fake()->randomElement([
                'Delivery attempted - recipient not available',
                'Address not found',
                'Package damaged in transit',
                'Customs clearance delay',
            ]),
        ]);
    }
}

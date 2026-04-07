<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => \App\Models\User::factory(),
            'delivery_mode_id' => fake()->randomNumber(3, true),
            'shipment_number' => 'SHP-' . fake()->unique()->numerify('######'),
            'source_type' => fake()->randomElement(['invoice', 'sales_order']),
            'source_id' => fake()->randomNumber(3, true),
            'contact_id' => fake()->randomNumber(3, true),
            'shipping_address' => [
                'line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->randomElement(['SA', 'AE', 'IN']),
            ],
            'billing_address' => [
                'line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->randomElement(['SA', 'AE', 'IN']),
            ],
            'tracking_number' => strtoupper(fake()->bothify('??#########??')),
            'carrier' => fake()->randomElement(['Aramex', 'SMSA', 'DHL', 'FedEx', 'UPS']),
            'tracking_url' => fake()->optional(0.5)->url(),
            'ship_date' => fake()->dateTimeBetween('-7 days', 'now'),
            'estimated_delivery' => fake()->dateTimeBetween('+1 day', '+14 days'),
            'actual_delivery' => null,
            'total_weight_kg' => fake()->randomFloat(2, 0.5, 50),
            'dimensions' => [
                'length' => fake()->numberBetween(10, 100),
                'width' => fake()->numberBetween(10, 80),
                'height' => fake()->numberBetween(5, 60),
                'unit' => 'cm',
            ],
            'shipping_cost' => fake()->randomFloat(2, 10, 200),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'status' => fake()->randomElement(['pending', 'picked_up', 'in_transit', 'delivered', 'returned']),
            'notes' => fake()->optional(0.3)->sentence(),
            'delivery_notes' => fake()->optional(0.2)->sentence(),
            'proof_of_delivery_path' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'actual_delivery' => null,
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(fn () => [
            'status' => 'in_transit',
            'actual_delivery' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'actual_delivery' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'status' => 'returned',
        ]);
    }
}

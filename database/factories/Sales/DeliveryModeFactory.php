<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\DeliveryMode;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryModeFactory extends Factory
{
    protected $model = DeliveryMode::class;

    public function definition(): array
    {
        $types = ['standard', 'express', 'same_day', 'pickup', 'freight', 'courier'];
        $type = fake()->randomElement($types);

        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst(str_replace('_', ' ', $type)) . ' Delivery',
            'code' => strtoupper(fake()->unique()->bothify('DM-????##')),
            'type' => $type,
            'description' => fake()->optional(0.5)->sentence(),
            'icon' => fake()->optional(0.3)->randomElement(['truck', 'plane', 'store', 'box']),
            'pricing_type' => fake()->randomElement(['flat', 'weight_based', 'value_based', 'zone_based']),
            'flat_rate' => fake()->randomFloat(2, 5, 50),
            'pricing_rules' => null,
            'min_delivery_days' => fake()->numberBetween(1, 3),
            'max_delivery_days' => fake()->numberBetween(3, 14),
            'delivery_time_label' => fake()->randomElement(['1-3 business days', '3-5 business days', 'Same day', 'Next day']),
            'free_shipping_min' => fake()->optional(0.4)->randomFloat(2, 100, 500),
            'max_weight_kg' => fake()->optional(0.5)->randomFloat(2, 10, 100),
            'max_value' => fake()->optional(0.3)->randomFloat(2, 5000, 50000),
            'supported_zones' => null,
            'excluded_products' => null,
            'tracking_enabled' => fake()->boolean(70),
            'carrier_provider' => fake()->optional(0.5)->randomElement(['aramex', 'smsa', 'dhl', 'fedex']),
            'carrier_config' => null,
            'available_days' => [1, 2, 3, 4, 5],
            'cutoff_time' => fake()->optional(0.5)->time('H:i'),
            'requires_address' => $type !== 'pickup',
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => [
            'name' => 'Standard Delivery',
            'type' => 'standard',
            'min_delivery_days' => 3,
            'max_delivery_days' => 7,
            'delivery_time_label' => '3-7 business days',
        ]);
    }

    public function express(): static
    {
        return $this->state(fn () => [
            'name' => 'Express Delivery',
            'type' => 'express',
            'min_delivery_days' => 1,
            'max_delivery_days' => 2,
            'delivery_time_label' => '1-2 business days',
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn () => [
            'name' => 'Store Pickup',
            'type' => 'pickup',
            'flat_rate' => '0.00',
            'min_delivery_days' => 0,
            'max_delivery_days' => 0,
            'delivery_time_label' => 'Same day',
            'requires_address' => false,
            'tracking_enabled' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn () => [
            'flat_rate' => '0.00',
            'free_shipping_min' => '0.00',
        ]);
    }
}

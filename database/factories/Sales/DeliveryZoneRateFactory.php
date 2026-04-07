<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\DeliveryZoneRate;
use App\Models\Sales\DeliveryMode;
use App\Models\Sales\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryZoneRateFactory extends Factory
{
    protected $model = DeliveryZoneRate::class;

    public function definition(): array
    {
        return [
            'delivery_mode_id' => DeliveryMode::factory(),
            'zone_id' => DeliveryZone::factory(),
            'rate' => fake()->randomFloat(2, 5, 100),
            'additional_item_rate' => fake()->randomFloat(2, 1, 20),
            'min_weight' => 0,
            'max_weight' => fake()->optional(0.5)->randomFloat(2, 5, 50),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
        ];
    }
}

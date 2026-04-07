<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\MeteredPricingTier;
use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeteredPricingTierFactory extends Factory
{
    protected $model = MeteredPricingTier::class;

    public function definition(): array
    {
        return [
            'plan_id' => SubscriptionPlan::factory(),
            'metric_type' => fake()->randomElement(['api_calls', 'storage', 'users', 'invoices']),
            'from_quantity' => fake()->numberBetween(0, 100),
            'to_quantity' => fake()->optional(0.7)->numberBetween(101, 10000),
            'price_per_unit' => fake()->randomFloat(6, 0.001, 10),
            'unit_label' => fake()->randomElement(['call', 'GB', 'user', 'invoice']),
        ];
    }
}
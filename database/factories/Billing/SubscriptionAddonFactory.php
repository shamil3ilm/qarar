<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\SubscriptionAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionAddonFactory extends Factory
{
    protected $model = SubscriptionAddon::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Addon',
            'code' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.5)->sentence(),
            'addon_type' => fake()->randomElement(['feature', 'storage', 'users']),
            'price' => fake()->randomFloat(2, 5, 200),
            'pricing_model' => fake()->randomElement(['flat', 'per_unit']),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'unit_quantity' => fake()->numberBetween(1, 100),
            'unit_label' => fake()->randomElement(['user', 'GB', 'unit']),
            'compatible_plans' => null,
            'is_active' => true,
        ];
    }
}
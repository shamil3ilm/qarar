<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DISC-####??')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'max_discount_amount' => fake()->optional(0.5)->randomFloat(2, 50, 500),
            'min_order_amount' => fake()->optional(0.3)->randomFloat(2, 100, 1000),
            'applies_to' => fake()->randomElement(['all', 'specific_plans']),
            'applicable_plan_ids' => null,
            'max_uses' => fake()->optional(0.5)->numberBetween(10, 1000),
            'max_uses_per_org' => fake()->optional(0.5)->numberBetween(1, 5),
            'times_used' => 0,
            'starts_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+1 year'),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
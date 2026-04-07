<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Starter', 'Professional', 'Enterprise', 'Business']),
            'code' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'tier' => fake()->randomElement(['starter', 'professional', 'enterprise']),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'base_price' => fake()->randomFloat(2, 29, 999),
            'currency_code' => 'USD',
            'max_users' => fake()->numberBetween(5, 500),
            'max_branches' => fake()->numberBetween(1, 50),
            'storage_limit_mb' => fake()->numberBetween(1024, 102400),
            'max_invoices_per_month' => fake()->numberBetween(100, 100000),
            'max_products' => fake()->numberBetween(100, 100000),
            'max_customers' => fake()->numberBetween(100, 100000),
            'max_employees' => fake()->numberBetween(10, 1000),
            'api_calls_per_month' => fake()->numberBetween(1000, 1000000),
            'included_modules' => ['sales', 'inventory', 'accounting'],
            'features' => ['multi_currency', 'api_access'],
            'trial_days' => 14,
            'trial_requires_card' => false,
            'is_public' => true,
            'is_popular' => false,
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
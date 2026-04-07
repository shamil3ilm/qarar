<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->randomElement(['Starter', 'Professional', 'Enterprise']),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 29, 499),
            'yearly_price' => fake()->randomFloat(2, 290, 4990),
            'currency_code' => 'USD',
            'max_users' => fake()->numberBetween(5, 500),
            'max_branches' => fake()->numberBetween(1, 50),
            'max_products' => fake()->numberBetween(100, 100000),
            'max_invoices_per_month' => fake()->numberBetween(100, 100000),
            'storage_gb' => fake()->numberBetween(5, 500),
            'features' => ['multi_currency', 'api_access'],
            'limits' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

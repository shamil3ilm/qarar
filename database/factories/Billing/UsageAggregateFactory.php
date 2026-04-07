<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\UsageAggregate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageAggregateFactory extends Factory
{
    protected $model = UsageAggregate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'metric_type' => fake()->randomElement(['api_calls', 'storage', 'invoices']),
            'period_type' => fake()->randomElement(['daily', 'monthly']),
            'period' => now()->format('Y-m'),
            'total_quantity' => fake()->numberBetween(100, 10000),
            'peak_quantity' => fake()->numberBetween(50, 5000),
            'average_quantity' => fake()->randomFloat(2, 10, 1000),
            'breakdown' => null,
        ];
    }
}
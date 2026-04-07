<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\UsageMetric;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageMetricFactory extends Factory
{
    protected $model = UsageMetric::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'metric_type' => fake()->randomElement(['api_calls', 'storage', 'invoices', 'users']),
            'quantity' => fake()->numberBetween(1, 1000),
            'metric_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'billing_period' => now()->format('Y-m'),
        ];
    }
}
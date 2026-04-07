<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\UsageAlert;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageAlertFactory extends Factory
{
    protected $model = UsageAlert::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'metric_type' => fake()->randomElement(['api_calls', 'storage', 'invoices', 'users']),
            'threshold_percent' => fake()->randomElement([80, 90, 100]),
            'threshold_value' => fake()->numberBetween(100, 10000),
            'current_value' => fake()->numberBetween(0, 10000),
            'status' => fake()->randomElement(['active', 'triggered', 'resolved']),
            'notified_at' => null,
            'resolved_at' => null,
        ];
    }
}
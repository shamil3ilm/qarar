<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\UsageSnapshot;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageSnapshotFactory extends Factory
{
    protected $model = UsageSnapshot::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'users_count' => fake()->numberBetween(1, 100),
            'branches_count' => fake()->numberBetween(1, 20),
            'storage_used_mb' => fake()->numberBetween(10, 50000),
            'invoices_this_month' => fake()->numberBetween(0, 5000),
            'products_count' => fake()->numberBetween(0, 10000),
            'customers_count' => fake()->numberBetween(0, 5000),
            'employees_count' => fake()->numberBetween(1, 500),
            'api_calls_this_month' => fake()->numberBetween(0, 100000),
            'snapshot_at' => now(),
        ];
    }
}
<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\ApiRequestLog;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'endpoint' => '/api/v1/' . fake()->randomElement(['invoices', 'products', 'contacts']),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'response_status' => fake()->randomElement([200, 201, 400, 404, 500]),
            'response_time_ms' => fake()->numberBetween(10, 3000),
            'ip_address' => fake()->ipv4(),
            'api_version' => 'v1',
            'requested_at' => now(),
        ];
    }
}
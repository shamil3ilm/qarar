<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ActivityLog;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'branch_id' => null,
            'action' => fake()->randomElement(['create', 'update', 'delete', 'view', 'export']),
            'entity_type' => fake()->randomElement(['invoice', 'product', 'contact']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'entity_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'request_url' => '/api/v1/' . fake()->slug(),
            'session_id' => fake()->optional(0.5)->uuid(),
            'module' => fake()->randomElement(['sales', 'inventory', 'accounting']),
            'severity' => fake()->randomElement(['info', 'warning', 'error']),
            'is_system' => false,
        ];
    }
}

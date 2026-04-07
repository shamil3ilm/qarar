<?php

declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\AuditLog;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'auditable_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Inventory\Product']),
            'auditable_id' => fake()->numberBetween(1, 1000),
            'event' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => '/api/v1/' . fake()->slug(),
        ];
    }
}

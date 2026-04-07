<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformAdminActivity;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAdminActivityFactory extends Factory
{
    protected $model = PlatformAdminActivity::class;

    public function definition(): array
    {
        return [
            'admin_id' => PlatformAdmin::factory(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'login', 'view']),
            'entity_type' => fake()->randomElement(['organization', 'subscription', 'user']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'organization_id' => null,
            'old_values' => null,
            'new_values' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
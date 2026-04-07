<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ModuleAccessLog;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleAccessLogFactory extends Factory
{
    protected $model = ModuleAccessLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'module_id' => null,
            'action' => fake()->randomElement(['view', 'create', 'edit', 'delete']),
            'entity_type' => fake()->optional(0.5)->randomElement(['invoice', 'product']),
            'entity_id' => fake()->optional(0.5)->numberBetween(1, 1000),
            'was_allowed' => true,
            'denial_reason' => null,
            'ip_address' => fake()->ipv4(),
            'accessed_at' => now(),
        ];
    }
}

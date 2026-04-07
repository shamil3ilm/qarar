<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\OrganizationModule;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationModuleFactory extends Factory
{
    protected $model = OrganizationModule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'module_code' => fake()->randomElement(['sales', 'inventory', 'accounting', 'hr', 'crm']),
            'is_enabled' => true,
            'enabled_features' => [],
            'settings' => null,
            'enabled_at' => now(),
            'disabled_at' => null,
            'enabled_by' => null,
        ];
    }
}

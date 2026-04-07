<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\OrganizationModuleAccess;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationModuleAccessFactory extends Factory
{
    protected $model = OrganizationModuleAccess::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'module_id' => null,
            'is_enabled' => true,
            'enabled_at' => now(),
            'disabled_at' => null,
            'enabled_by' => null,
            'config' => null,
        ];
    }
}

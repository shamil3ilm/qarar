<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\OrganizationStatusHistory;
use App\Models\Core\Organization;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationStatusHistoryFactory extends Factory
{
    protected $model = OrganizationStatusHistory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'admin_id' => PlatformAdmin::factory(),
            'status_from' => fake()->randomElement(['active', 'suspended', 'trial']),
            'status_to' => fake()->randomElement(['active', 'suspended', 'cancelled']),
            'reason' => fake()->optional(0.5)->sentence(),
            'metadata' => null,
        ];
    }
}
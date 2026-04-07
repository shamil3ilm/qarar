<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\DashboardLayout;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardLayoutFactory extends Factory
{
    protected $model = DashboardLayout::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Dashboard',
            'type' => fake()->randomElement(['main', 'sales', 'inventory', 'hr']),
            'widgets' => [['code' => 'revenue_chart', 'size' => 'large']],
            'layout' => ['columns' => 3],
            'is_default' => false,
            'is_shared' => false,
        ];
    }
}

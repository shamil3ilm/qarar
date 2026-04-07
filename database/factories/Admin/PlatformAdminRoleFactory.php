<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformAdminRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAdminRoleFactory extends Factory
{
    protected $model = PlatformAdminRole::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Role',
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.5)->sentence(),
            'permissions' => ['organizations.view', 'organizations.edit'],
            'is_system' => false,
        ];
    }
}
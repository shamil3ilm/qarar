<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformPermissionFactory extends Factory
{
    protected $model = PlatformPermission::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'module' => fake()->randomElement(['organizations', 'billing', 'support', 'system']),
            'description' => fake()->optional(0.5)->sentence(),
        ];
    }
}
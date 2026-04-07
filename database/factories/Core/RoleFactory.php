<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Organization;
use App\Models\Core\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
        ]);
    }
}

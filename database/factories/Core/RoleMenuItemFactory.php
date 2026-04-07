<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\RoleMenuItem;
use App\Models\Core\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleMenuItemFactory extends Factory
{
    protected $model = RoleMenuItem::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'module_id' => null,
            'menu_label' => fake()->words(2, true),
            'menu_icon' => fake()->optional(0.5)->word(),
            'route_name' => fake()->slug(2),
            'parent_menu' => null,
            'position' => fake()->numberBetween(1, 20),
            'is_visible' => true,
            'is_pinned' => false,
        ];
    }
}

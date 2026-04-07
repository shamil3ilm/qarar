<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ModuleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleDefinitionFactory extends Factory
{
    protected $model = ModuleDefinition::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->slug(2),
            'group' => fake()->randomElement(['core', 'sales', 'inventory', 'accounting', 'hr']),
            'description' => fake()->optional(0.5)->sentence(),
            'icon' => fake()->optional(0.3)->word(),
            'sub_modules' => null,
            'required_modules' => null,
            'min_subscription_tier' => fake()->randomElement(['starter', 'professional', 'enterprise']),
            'is_core' => false,
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 50),
        ];
    }
}

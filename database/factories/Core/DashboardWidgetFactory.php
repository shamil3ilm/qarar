<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\DashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'category' => fake()->randomElement(['sales', 'inventory', 'accounting', 'hr']),
            'type' => fake()->randomElement(['chart', 'counter', 'table', 'list']),
            'default_config' => ['period' => 'monthly'],
            'available_sizes' => ['small', 'medium', 'large'],
            'data_source' => fake()->slug(2),
            'permission' => null,
            'module' => fake()->randomElement(['sales', 'inventory', 'accounting']),
            'is_premium' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}

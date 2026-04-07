<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskBoardTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskBoardTemplateFactory extends Factory
{
    protected $model = TaskBoardTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'description' => fake()->optional(0.3)->sentence(),
            'board_type' => fake()->randomElement(['kanban', 'scrum']),
            'columns' => [['name' => 'To Do', 'color' => '#ddd'], ['name' => 'Done', 'color' => '#0f0']],
            'labels' => [['name' => 'Bug', 'color' => '#f00'], ['name' => 'Feature', 'color' => '#00f']],
            'is_system' => false,
            'is_active' => true,
        ];
    }
}

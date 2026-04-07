<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskBoard;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskBoardFactory extends Factory
{
    protected $model = TaskBoard::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => fake()->words(3, true) . ' Board',
            'description' => fake()->optional(0.3)->sentence(),
            'board_type' => fake()->randomElement(['kanban', 'scrum', 'custom']),
            'visibility' => fake()->randomElement(['public', 'private', 'team']),
            'color' => fake()->optional(0.3)->hexColor(),
            'icon' => null,
            'is_template' => false,
            'is_archived' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}

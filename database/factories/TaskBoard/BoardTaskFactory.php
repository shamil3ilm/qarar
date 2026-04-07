<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\BoardTask;
use App\Models\Core\Organization;
use App\Models\TaskBoard\TaskBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardTaskFactory extends Factory
{
    protected $model = BoardTask::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'board_id' => TaskBoard::factory(),
            'column_id' => null,
            'parent_task_id' => null,
            'task_number' => 'TASK-' . fake()->unique()->numerify('####'),
            'title' => fake()->sentence(6),
            'description' => fake()->optional(0.5)->paragraph(),
            'task_type' => fake()->randomElement(['task', 'bug', 'feature', 'story', 'epic']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => fake()->randomElement(['backlog', 'todo', 'in_progress', 'review', 'done']),
            'assignee_id' => null,
            'reporter_id' => null,
            'start_date' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+2 months'),
            'completed_at' => null,
            'estimated_hours' => fake()->optional(0.5)->randomFloat(2, 1, 40),
            'actual_hours' => fake()->optional(0.3)->randomFloat(2, 1, 60),
            'story_points' => fake()->optional(0.5)->randomElement([1, 2, 3, 5, 8, 13]),
            'position' => fake()->numberBetween(0, 100),
            'tags' => null,
        ];
    }
}

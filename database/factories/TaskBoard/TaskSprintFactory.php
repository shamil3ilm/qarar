<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskSprint;
use App\Models\TaskBoard\TaskBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskSprintFactory extends Factory
{
    protected $model = TaskSprint::class;

    public function definition(): array
    {
        return [
            'board_id' => TaskBoard::factory(),
            'name' => 'Sprint ' . fake()->numberBetween(1, 50),
            'goal' => fake()->optional(0.5)->sentence(),
            'start_date' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'end_date' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'status' => fake()->randomElement(['planning', 'active', 'completed', 'cancelled']),
            'total_points' => fake()->numberBetween(0, 100),
            'completed_points' => fake()->numberBetween(0, 50),
            'started_at' => fake()->optional(0.5)->dateTimeBetween('-2 weeks', 'now'),
            'completed_at' => null,
            'created_by' => null,
        ];
    }
}

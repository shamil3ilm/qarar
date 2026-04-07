<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskActivity;
use App\Models\TaskBoard\BoardTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'user_id' => User::factory(),
            'activity_type' => fake()->randomElement(['created', 'status_changed', 'assigned', 'commented', 'updated']),
            'field_name' => fake()->optional(0.5)->randomElement(['status', 'assignee', 'priority', 'due_date']),
            'old_value' => fake()->optional(0.3)->word(),
            'new_value' => fake()->optional(0.3)->word(),
            'metadata' => null,
        ];
    }
}

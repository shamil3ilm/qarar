<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskDependency;
use App\Models\TaskBoard\BoardTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDependencyFactory extends Factory
{
    protected $model = TaskDependency::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'depends_on_task_id' => BoardTask::factory(),
            'dependency_type' => fake()->randomElement(['blocks', 'blocked_by', 'relates_to']),
        ];
    }
}

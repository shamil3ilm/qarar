<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskChecklist;
use App\Models\TaskBoard\BoardTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskChecklistFactory extends Factory
{
    protected $model = TaskChecklist::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'title' => fake()->sentence(4),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}

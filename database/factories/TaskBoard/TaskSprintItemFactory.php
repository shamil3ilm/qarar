<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskSprintItem;
use App\Models\TaskBoard\TaskSprint;
use App\Models\TaskBoard\BoardTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskSprintItemFactory extends Factory
{
    protected $model = TaskSprintItem::class;

    public function definition(): array
    {
        return [
            'sprint_id' => TaskSprint::factory(),
            'task_id' => BoardTask::factory(),
            'points' => fake()->randomElement([1, 2, 3, 5, 8, 13]),
            'added_at' => now(),
        ];
    }
}

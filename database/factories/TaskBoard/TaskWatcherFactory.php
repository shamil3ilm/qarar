<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskWatcher;
use App\Models\TaskBoard\BoardTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskWatcherFactory extends Factory
{
    protected $model = TaskWatcher::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'user_id' => User::factory(),
        ];
    }
}

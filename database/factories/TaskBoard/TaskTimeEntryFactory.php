<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskTimeEntry;
use App\Models\TaskBoard\BoardTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskTimeEntryFactory extends Factory
{
    protected $model = TaskTimeEntry::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'user_id' => User::factory(),
            'description' => fake()->optional(0.5)->sentence(),
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
            'duration_minutes' => fake()->numberBetween(15, 480),
            'is_billable' => fake()->boolean(50),
        ];
    }
}

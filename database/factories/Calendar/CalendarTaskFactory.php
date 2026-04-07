<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\CalendarTask;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarTaskFactory extends Factory
{
    protected $model = CalendarTask::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'assigned_to' => User::factory(),
            'created_by' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.5)->paragraph(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed', 'cancelled']),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'due_time' => fake()->optional(0.5)->time('H:i'),
            'completed_at' => null,
            'taskable_type' => null,
            'taskable_id' => null,
            'parent_task_id' => null,
            'progress' => fake()->numberBetween(0, 100),
            'tags' => null,
            'checklist' => null,
            'is_recurring' => false,
        ];
    }
}
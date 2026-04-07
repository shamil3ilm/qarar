<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\CalendarTaskComment;
use App\Models\Calendar\CalendarTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarTaskCommentFactory extends Factory
{
    protected $model = CalendarTaskComment::class;

    public function definition(): array
    {
        return [
            'task_id' => CalendarTask::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
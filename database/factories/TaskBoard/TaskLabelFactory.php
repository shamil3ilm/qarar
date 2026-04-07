<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskLabel;
use App\Models\TaskBoard\TaskBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskLabelFactory extends Factory
{
    protected $model = TaskLabel::class;

    public function definition(): array
    {
        return [
            'board_id' => TaskBoard::factory(),
            'name' => fake()->randomElement(['Bug', 'Feature', 'Enhancement', 'Documentation', 'Urgent']),
            'color' => fake()->hexColor(),
            'description' => fake()->optional(0.3)->sentence(),
        ];
    }
}

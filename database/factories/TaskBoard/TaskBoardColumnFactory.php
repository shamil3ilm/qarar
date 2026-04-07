<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskBoardColumn;
use App\Models\TaskBoard\TaskBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskBoardColumnFactory extends Factory
{
    protected $model = TaskBoardColumn::class;

    public function definition(): array
    {
        return [
            'board_id' => TaskBoard::factory(),
            'name' => fake()->randomElement(['Backlog', 'To Do', 'In Progress', 'Review', 'Done']),
            'color' => fake()->hexColor(),
            'position' => fake()->numberBetween(0, 10),
            'wip_limit' => fake()->optional(0.3)->numberBetween(3, 10),
            'is_done_column' => false,
            'is_default' => false,
        ];
    }
}

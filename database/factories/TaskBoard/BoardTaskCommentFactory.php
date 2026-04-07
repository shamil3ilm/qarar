<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\BoardTaskComment;
use App\Models\TaskBoard\BoardTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardTaskCommentFactory extends Factory
{
    protected $model = BoardTaskComment::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'parent_id' => null,
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
            'mentions' => null,
            'is_edited' => false,
            'edited_at' => null,
        ];
    }
}

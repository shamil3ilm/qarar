<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskBoardMember;
use App\Models\TaskBoard\TaskBoard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskBoardMemberFactory extends Factory
{
    protected $model = TaskBoardMember::class;

    public function definition(): array
    {
        return [
            'board_id' => TaskBoard::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['admin', 'member', 'viewer']),
            'joined_at' => now(),
        ];
    }
}

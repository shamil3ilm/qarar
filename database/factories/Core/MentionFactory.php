<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Mention;
use App\Models\Core\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MentionFactory extends Factory
{
    protected $model = Mention::class;

    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'user_id' => User::factory(),
            'is_read' => false,
            'read_at' => null,
        ];
    }
}

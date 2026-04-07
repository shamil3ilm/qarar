<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Comment;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'commentable_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Sales\Contact']),
            'commentable_id' => fake()->numberBetween(1, 1000),
            'parent_id' => null,
            'content' => fake()->paragraph(),
            'is_internal' => fake()->boolean(30),
            'is_pinned' => false,
        ];
    }
}

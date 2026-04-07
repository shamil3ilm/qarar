<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Notification;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['info', 'warning', 'error', 'success']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'icon' => null,
            'color' => null,
            'action_url' => fake()->optional(0.3)->url(),
            'action_text' => fake()->optional(0.3)->words(2, true),
            'notifiable_type' => null,
            'notifiable_id' => null,
            'data' => null,
            'channel' => fake()->randomElement(['database', 'email', 'push']),
            'read_at' => null,
            'sent_at' => now(),
        ];
    }
}

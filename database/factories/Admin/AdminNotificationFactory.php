<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\AdminNotification;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminNotificationFactory extends Factory
{
    protected $model = AdminNotification::class;

    public function definition(): array
    {
        return [
            'admin_id' => PlatformAdmin::factory(),
            'type' => fake()->randomElement(['info', 'warning', 'error', 'success']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'data' => null,
            'action_url' => fake()->optional(0.3)->url(),
            'is_read' => false,
            'read_at' => null,
            'is_dismissed' => false,
            'dismissed_at' => null,
        ];
    }
}
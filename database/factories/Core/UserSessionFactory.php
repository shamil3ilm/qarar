<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\UserSession;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => Str::random(40),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'location' => fake()->optional(0.5)->city(),
            'login_at' => now(),
            'last_activity_at' => now(),
            'logout_at' => null,
            'is_active' => true,
            'logout_reason' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformAdminSession;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAdminSessionFactory extends Factory
{
    protected $model = PlatformAdminSession::class;

    public function definition(): array
    {
        return [
            'admin_id' => PlatformAdmin::factory(),
            'session_token' => Str::random(64),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'Linux']),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(8),
            'is_revoked' => false,
        ];
    }
}
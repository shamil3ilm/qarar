<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.5)->phoneNumber(),
            'password' => Hash::make('password'),
            'role' => fake()->randomElement(['super_admin', 'admin', 'support']),
            'avatar' => null,
            'is_active' => true,
            'is_2fa_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'permissions' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
        ];
    }
}
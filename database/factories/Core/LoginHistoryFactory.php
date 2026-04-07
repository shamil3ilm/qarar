<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginHistoryFactory extends Factory
{
    protected $model = LoginHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status' => fake()->randomElement(['success', 'failed']),
            'failure_reason' => null,
            'attempted_at' => now(),
        ];
    }
}

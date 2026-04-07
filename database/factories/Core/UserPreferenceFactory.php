<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\UserPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->randomElement(['theme', 'language', 'timezone', 'date_format']),
            'value' => fake()->word(),
        ];
    }
}

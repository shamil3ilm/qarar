<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => fake()->randomElement(['string', 'boolean', 'integer', 'json']),
            'group' => fake()->randomElement(['general', 'email', 'security', 'billing']),
            'description' => fake()->optional(0.5)->sentence(),
            'is_public' => false,
            'updated_by' => null,
        ];
    }
}
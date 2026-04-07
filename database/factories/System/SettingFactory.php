<?php

declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\Setting;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'group' => fake()->randomElement(['general', 'sales', 'inventory', 'accounting']),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => fake()->randomElement(['string', 'boolean', 'integer', 'json']),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Activity;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'branch_id' => null,
            'subject_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Sales\Contact']),
            'subject_id' => fake()->numberBetween(1, 1000),
            'causer_type' => 'App\Models\User',
            'causer_id' => null,
            'event' => fake()->randomElement(['created', 'updated', 'deleted', 'viewed']),
            'description' => fake()->sentence(),
            'properties' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'source' => fake()->randomElement(['web', 'api', 'system']),
        ];
    }
}

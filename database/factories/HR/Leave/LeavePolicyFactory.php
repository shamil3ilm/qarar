<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeavePolicy;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Policy',
            'description' => fake()->optional(0.5)->sentence(),
            'policy_year_type' => fake()->randomElement(['calendar', 'fiscal', 'custom']),
            'year_start_date' => fake()->optional(0.3)->date(),
            'allow_negative_balance' => false,
            'require_approval' => true,
            'min_notice_days' => fake()->numberBetween(1, 7),
            'allow_half_day' => true,
            'allow_hourly' => false,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

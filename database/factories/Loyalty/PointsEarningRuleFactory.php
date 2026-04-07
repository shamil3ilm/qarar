<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\PointsEarningRule;
use App\Models\Core\Organization;
use App\Models\Loyalty\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointsEarningRuleFactory extends Factory
{
    protected $model = PointsEarningRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'name' => fake()->words(3, true) . ' Bonus',
            'description' => fake()->optional(0.5)->sentence(),
            'trigger_type' => fake()->randomElement(['purchase', 'signup', 'referral', 'birthday', 'review']),
            'bonus_points' => fake()->numberBetween(10, 500),
            'bonus_multiplier' => fake()->randomFloat(2, 1, 3),
            'conditions' => null,
            'starts_at' => fake()->optional(0.5)->dateTimeBetween('now', '+1 month'),
            'ends_at' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+6 months'),
            'is_active' => true,
        ];
    }
}

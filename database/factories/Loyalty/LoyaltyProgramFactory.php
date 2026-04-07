<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company() . ' Rewards',
            'description' => fake()->optional(0.5)->sentence(),
            'currency_name' => fake()->randomElement(['Points', 'Stars', 'Coins', 'Miles']),
            'currency_symbol' => fake()->randomElement(['pts', '★', '🪙', 'mi']),
            'point_value' => fake()->randomFloat(4, 0.01, 1),
            'earn_rate' => fake()->randomFloat(4, 1, 10),
            'min_redeem_points' => fake()->numberBetween(100, 1000),
            'points_expiry_days' => fake()->numberBetween(90, 730),
            'allow_partial_redeem' => true,
            'earn_on_tax' => false,
            'earn_on_shipping' => false,
            'is_active' => true,
        ];
    }
}

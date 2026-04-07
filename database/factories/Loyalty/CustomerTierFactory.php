<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\CustomerTier;
use App\Models\Core\Organization;
use App\Models\Loyalty\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerTierFactory extends Factory
{
    protected $model = CustomerTier::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'name' => fake()->randomElement(['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond']),
            'code' => strtoupper(fake()->unique()->lexify('TIER-???')),
            'color' => fake()->hexColor(),
            'icon' => null,
            'qualification_type' => fake()->randomElement(['spending', 'points']),
            'min_spending' => fake()->randomFloat(2, 0, 50000),
            'min_points' => fake()->numberBetween(0, 10000),
            'qualification_period_months' => 12,
            'earn_rate_multiplier' => fake()->randomFloat(2, 1, 3),
            'discount_percent' => fake()->randomFloat(2, 0, 15),
            'free_shipping' => fake()->boolean(30),
            'priority_support_level' => null,
            'perks' => null,
            'auto_upgrade' => true,
            'auto_downgrade' => true,
            'grace_period_days' => 30,
            'tier_level' => fake()->numberBetween(1, 5),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\CustomerLoyaltyAccount;
use App\Models\Core\Organization;
use App\Models\Loyalty\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerLoyaltyAccountFactory extends Factory
{
    protected $model = CustomerLoyaltyAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contact_id' => null,
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'customer_tier_id' => null,
            'membership_number' => 'MBR-' . fake()->unique()->numerify('######'),
            'total_earned_points' => fake()->numberBetween(0, 50000),
            'total_redeemed_points' => fake()->numberBetween(0, 10000),
            'total_expired_points' => fake()->numberBetween(0, 5000),
            'available_points' => fake()->numberBetween(0, 35000),
            'pending_points' => fake()->numberBetween(0, 1000),
            'total_spending' => fake()->randomFloat(2, 0, 500000),
            'spending_this_period' => fake()->randomFloat(2, 0, 50000),
            'enrolled_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'tier_qualified_at' => fake()->optional(0.5)->dateTimeBetween('-1 year', 'now'),
            'tier_expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+1 year'),
            'last_activity_at' => fake()->optional(0.7)->dateTimeBetween('-3 months', 'now'),
            'is_active' => true,
        ];
    }
}

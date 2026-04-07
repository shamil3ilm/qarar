<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\CustomerLoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointsTransactionFactory extends Factory
{
    protected $model = PointsTransaction::class;

    public function definition(): array
    {
        return [
            'loyalty_account_id' => CustomerLoyaltyAccount::factory(),
            'transaction_type' => fake()->randomElement(['earn', 'redeem', 'expire', 'adjust']),
            'points' => fake()->numberBetween(1, 5000),
            'balance_before' => fake()->numberBetween(0, 50000),
            'balance_after' => fake()->numberBetween(0, 50000),
            'description' => fake()->sentence(),
            'source_type' => null,
            'source_id' => null,
            'source_amount' => fake()->optional(0.5)->randomFloat(2, 10, 5000),
            'earn_multiplier' => fake()->optional(0.3)->randomFloat(2, 1, 3),
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+3 months', '+2 years'),
            'is_expired' => false,
            'created_by' => null,
        ];
    }
}

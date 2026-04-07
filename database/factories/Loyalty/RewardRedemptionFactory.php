<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\RewardRedemption;
use App\Models\Loyalty\CustomerLoyaltyAccount;
use App\Models\Loyalty\RewardsCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class RewardRedemptionFactory extends Factory
{
    protected $model = RewardRedemption::class;

    public function definition(): array
    {
        return [
            'loyalty_account_id' => CustomerLoyaltyAccount::factory(),
            'reward_id' => RewardsCatalogItem::factory(),
            'points_transaction_id' => null,
            'points_spent' => fake()->numberBetween(100, 10000),
            'status' => fake()->randomElement(['pending', 'fulfilled', 'cancelled', 'expired']),
            'redemption_code' => strtoupper(fake()->bothify('RDM-####-????')),
            'invoice_id' => null,
            'fulfilled_at' => null,
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+6 months'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\SubscriptionAddonPurchase;
use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionAddonPurchaseFactory extends Factory
{
    protected $model = SubscriptionAddonPurchase::class;

    public function definition(): array
    {
        return [
            'subscription_id' => OrganizationSubscription::factory(),
            'addon_id' => SubscriptionAddon::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 5, 200),
            'total_price' => fake()->randomFloat(2, 5, 2000),
            'starts_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'status' => fake()->randomElement(['active', 'cancelled', 'expired']),
        ];
    }
}
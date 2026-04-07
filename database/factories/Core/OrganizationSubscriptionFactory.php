<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\OrganizationSubscription;
use App\Models\Core\Organization;
use App\Models\Core\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationSubscriptionFactory extends Factory
{
    protected $model = OrganizationSubscription::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => fake()->randomElement(['active', 'trial', 'cancelled', 'expired']),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'started_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'cancelled_at' => null,
            'trial_ends_at' => null,
            'custom_limits' => null,
            'addons' => null,
            'payment_method' => fake()->randomElement(['card', 'bank_transfer']),
            'external_subscription_id' => null,
        ];
    }
}

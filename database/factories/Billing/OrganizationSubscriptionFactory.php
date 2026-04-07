<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\OrganizationSubscription;
use App\Models\Core\Organization;
use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationSubscriptionFactory extends Factory
{
    protected $model = OrganizationSubscription::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => fake()->randomElement(['active', 'trial', 'cancelled', 'expired', 'past_due']),
            'starts_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'ends_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'base_price' => fake()->randomFloat(2, 29, 999),
            'discount_amount' => 0,
            'discount_percent' => 0,
            'discount_code' => null,
            'max_users' => fake()->numberBetween(5, 100),
            'max_branches' => fake()->numberBetween(1, 20),
            'storage_limit_mb' => fake()->numberBetween(1024, 102400),
            'max_invoices_per_month' => fake()->numberBetween(100, 10000),
            'enabled_modules' => ['sales', 'inventory', 'accounting'],
            'enabled_features' => [],
            'auto_renew' => true,
            'payment_method_id' => null,
            'next_billing_date' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ];
    }
}
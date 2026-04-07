<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingPaymentMethod;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingPaymentMethodFactory extends Factory
{
    protected $model = BillingPaymentMethod::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => fake()->randomElement(['card', 'bank_account']),
            'provider' => 'stripe',
            'provider_payment_method_id' => 'pm_' . fake()->unique()->lexify('????????????????'),
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'card_last_four' => fake()->numerify('####'),
            'card_exp_month' => fake()->numberBetween(1, 12),
            'card_exp_year' => fake()->numberBetween(2025, 2030),
            'bank_name' => null,
            'bank_account_last_four' => null,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
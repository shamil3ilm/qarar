<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingCreditTransaction;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingCreditTransactionFactory extends Factory
{
    protected $model = BillingCreditTransaction::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'transaction_type' => fake()->randomElement(['credit', 'debit']),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'balance_before' => fake()->randomFloat(2, 0, 5000),
            'balance_after' => fake()->randomFloat(2, 0, 5000),
            'description' => fake()->sentence(),
            'invoice_id' => null,
            'payment_id' => null,
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'created_by' => null,
        ];
    }
}
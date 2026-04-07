<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingPayment;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingPaymentFactory extends Factory
{
    protected $model = BillingPayment::class;

    public function definition(): array
    {
        return [
            'transaction_id' => fake()->unique()->uuid(),
            'organization_id' => Organization::factory(),
            'invoice_id' => null,
            'payment_method_id' => null,
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency_code' => 'USD',
            'payment_type' => fake()->randomElement(['subscription', 'one_time', 'addon']),
            'provider' => fake()->randomElement(['stripe', 'paypal']),
            'provider_transaction_id' => fake()->uuid(),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'processed_at' => now(),
            'failure_reason' => null,
            'failure_code' => null,
            'is_refunded' => false,
            'refunded_amount' => 0,
            'refunded_at' => null,
            'refund_reason' => null,
            'provider_response' => null,
        ];
    }
}
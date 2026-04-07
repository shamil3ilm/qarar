<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\OnlinePayment;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnlinePaymentFactory extends Factory
{
    protected $model = OnlinePayment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'gateway_id' => null,
            'payable_type' => 'App\Models\Sales\Invoice',
            'payable_id' => fake()->numberBetween(1, 1000),
            'external_payment_id' => fake()->uuid(),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'USD']),
            'amount' => fake()->randomFloat(2, 50, 10000),
            'fee_amount' => fake()->randomFloat(2, 0, 100),
            'net_amount' => fake()->randomFloat(2, 50, 10000),
            'payment_method' => fake()->randomElement(['card', 'bank', 'wallet']),
            'card_brand' => fake()->optional(0.5)->randomElement(['visa', 'mastercard']),
            'card_last4' => fake()->optional(0.5)->numerify('####'),
            'gateway_response' => null,
            'failure_reason' => null,
            'ip_address' => fake()->ipv4(),
            'payment_received_id' => null,
        ];
    }
}

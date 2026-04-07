<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\PaymentMode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentModeFactory extends Factory
{
    protected $model = PaymentMode::class;

    public function definition(): array
    {
        $types = ['cash', 'bank_transfer', 'credit_card', 'debit_card', 'cheque', 'online', 'wallet', 'other'];
        $type = fake()->randomElement($types);

        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst($type) . ' Payment',
            'code' => strtoupper(fake()->unique()->bothify('PM-????##')),
            'type' => $type,
            'description' => fake()->optional(0.5)->sentence(),
            'icon' => fake()->optional(0.3)->randomElement(['cash', 'credit-card', 'bank', 'wallet']),
            'bank_account_id' => null,
            'account_id' => null,
            'is_online' => in_array($type, ['credit_card', 'debit_card', 'online']),
            'requires_reference' => in_array($type, ['bank_transfer', 'cheque', 'online']),
            'requires_approval' => fake()->boolean(20),
            'surcharge_percent' => fake()->boolean(30) ? fake()->randomFloat(2, 0.5, 3.0) : 0,
            'surcharge_flat' => fake()->boolean(20) ? fake()->randomFloat(2, 1, 10) : 0,
            'min_amount' => fake()->optional(0.3)->randomFloat(2, 1, 50),
            'max_amount' => fake()->optional(0.3)->randomFloat(2, 10000, 100000),
            'supported_currencies' => ['SAR', 'AED', 'USD'],
            'gateway_provider' => in_array($type, ['credit_card', 'online']) ? fake()->randomElement(['stripe', 'payfort', 'tap']) : null,
            'gateway_config' => null,
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'name' => 'Cash',
            'type' => 'cash',
            'is_online' => false,
            'requires_reference' => false,
            'surcharge_percent' => 0,
            'surcharge_flat' => 0,
            'gateway_provider' => null,
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'name' => 'Credit Card',
            'type' => 'credit_card',
            'is_online' => true,
            'requires_reference' => true,
            'gateway_provider' => 'stripe',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn () => [
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'is_online' => false,
            'requires_reference' => true,
            'gateway_provider' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}

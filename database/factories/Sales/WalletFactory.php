<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contact_id' => Contact::factory(),
            'wallet_type' => fake()->randomElement([
                Wallet::TYPE_CUSTOMER,
                Wallet::TYPE_SUPPLIER,
            ]),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'balance' => fake()->randomFloat(2, 0, 50000),
            'credit_limit' => fake()->randomFloat(2, 0, 10000),
            'is_active' => true,
        ];
    }

    public function customerWallet(): static
    {
        return $this->state(fn () => [
            'wallet_type' => Wallet::TYPE_CUSTOMER,
        ]);
    }

    public function supplierWallet(): static
    {
        return $this->state(fn () => [
            'wallet_type' => Wallet::TYPE_SUPPLIER,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function withBalance(float $amount): static
    {
        return $this->state(fn () => [
            'balance' => $amount,
        ]);
    }

    public function zeroBalance(): static
    {
        return $this->state(fn () => [
            'balance' => '0.00',
        ]);
    }
}

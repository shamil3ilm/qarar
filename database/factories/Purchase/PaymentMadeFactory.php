<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\PaymentMade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMade>
 */
class PaymentMadeFactory extends Factory
{
    protected $model = PaymentMade::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(4, 100, 50000);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'payment_number' => 'PAY-' . fake()->unique()->numerify('######'),
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'supplier_id' => null,
            'bank_account_id' => null,
            'payment_method' => fake()->randomElement([
                PaymentMade::METHOD_CASH,
                PaymentMade::METHOD_BANK_TRANSFER,
                PaymentMade::METHOD_CHEQUE,
                PaymentMade::METHOD_CREDIT_CARD,
                PaymentMade::METHOD_ONLINE,
            ]),
            'amount' => $amount,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'base_amount' => $amount,
            'reference' => fake()->optional(0.5)->bothify('REF-####-???'),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => PaymentMade::STATUS_PENDING,
            'journal_entry_id' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentMade::STATUS_PENDING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentMade::STATUS_COMPLETED,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => PaymentMade::STATUS_VOIDED,
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => PaymentMade::STATUS_BOUNCED,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentMade::METHOD_CASH,
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
        ]);
    }

    public function cheque(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentMade::METHOD_CHEQUE,
        ]);
    }

    public function withExchangeRate(string $currency, float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => $currency,
            'exchange_rate' => number_format($rate, 8, '.', ''),
            'base_amount' => round((float) $attributes['amount'] * $rate, 4),
        ]);
    }
}

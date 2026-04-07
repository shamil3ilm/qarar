<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\PaymentReceived;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentReceivedFactory extends Factory
{
    protected $model = PaymentReceived::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(4, 50, 25000);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'payment_number' => 'PMT-' . fake()->unique()->numerify('######'),
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'customer_id' => \App\Models\Sales\Contact::factory(),
            'payment_method' => fake()->randomElement([
                PaymentReceived::METHOD_CASH,
                PaymentReceived::METHOD_BANK_TRANSFER,
                PaymentReceived::METHOD_CHEQUE,
                PaymentReceived::METHOD_CREDIT_CARD,
                PaymentReceived::METHOD_ONLINE,
            ]),
            'amount' => $amount,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'base_amount' => $amount,
            'reference' => fake()->optional(0.5)->bothify('REF-####??'),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => PaymentReceived::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentReceived::STATUS_PENDING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentReceived::STATUS_COMPLETED,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => PaymentReceived::STATUS_VOIDED,
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => PaymentReceived::STATUS_BOUNCED,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentReceived::METHOD_CASH,
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
        ]);
    }
}

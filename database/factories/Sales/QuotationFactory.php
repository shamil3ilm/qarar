<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(4, 100, 50000);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 4);
        $total = round($subtotal + $taxAmount, 4);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'quotation_number' => 'QTN-' . fake()->unique()->numerify('######'),
            'customer_id' => \App\Models\Sales\Contact::factory(),
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'billing_address' => fake()->address(),
            'shipping_address' => fake()->address(),
            'quotation_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'valid_until' => fake()->dateTimeBetween('+7 days', '+60 days'),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => $taxAmount,
            'total' => $total,
            'status' => Quotation::STATUS_DRAFT,
            'notes' => fake()->optional(0.3)->sentence(),
            'terms_and_conditions' => fake()->optional(0.3)->paragraph(),
            'reference' => fake()->optional(0.4)->bothify('REF-####??'),
            'version' => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_DRAFT,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_SENT,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_ACCEPTED,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_DECLINED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_EXPIRED,
            'valid_until' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn () => [
            'status' => Quotation::STATUS_CONVERTED,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(4, 100, 50000);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 4);
        $total = round($subtotal + $taxAmount, 4);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'customer_id' => Contact::factory(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('######'),
            'invoice_type' => Invoice::TYPE_STANDARD,
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'customer_tax_number' => fake()->numerify('###############'),
            'billing_address' => fake()->address(),
            'shipping_address' => fake()->address(),
            'invoice_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => $taxAmount,
            'total' => $total,
            'base_total' => $total,
            'amount_paid' => '0.0000',
            'amount_due' => $total,
            'status' => Invoice::STATUS_DRAFT,
            'compliance_status' => Invoice::COMPLIANCE_NOT_APPLICABLE,
            'is_reverse_charge' => false,
            'notes' => fake()->optional(0.3)->sentence(),
            'terms_and_conditions' => fake()->optional(0.3)->paragraph(),
            'reference' => fake()->optional(0.4)->bothify('REF-####??'),
            'version' => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_SENT,
            'amount_paid' => '0.0000',
            'amount_due' => $attributes['total'] ?? '1000.0000',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'amount_paid' => $attributes['total'] ?? '1000.0000',
            'amount_due' => '0.0000',
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_VOIDED,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PARTIAL,
            'amount_paid' => round(((float) ($attributes['total'] ?? 1000)) * 0.5, 4),
            'amount_due' => round(((float) ($attributes['total'] ?? 1000)) * 0.5, 4),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_OVERDUE,
            'due_date' => fake()->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }

    public function creditNote(): static
    {
        return $this->state(fn () => [
            'invoice_type' => Invoice::TYPE_CREDIT_NOTE,
        ]);
    }

    public function simplified(): static
    {
        return $this->state(fn () => [
            'invoice_type' => Invoice::TYPE_SIMPLIFIED,
        ]);
    }
}

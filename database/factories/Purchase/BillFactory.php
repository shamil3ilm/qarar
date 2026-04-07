<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(4, 500, 50000);
        $taxAmount = round($subtotal * 0.15, 4);
        $total = round($subtotal + $taxAmount, 4);
        $billDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'bill_number' => 'BILL-' . fake()->unique()->numerify('######'),
            'supplier_invoice_number' => fake()->optional(0.7)->bothify('INV-####'),
            'bill_type' => Bill::TYPE_STANDARD,
            'purchase_order_id' => null,
            'original_bill_id' => null,
            'supplier_id' => null,
            'supplier_name' => fake()->company(),
            'supplier_tax_number' => fake()->optional(0.6)->numerify('###############'),
            'supplier_address' => fake()->optional(0.5)->address(),
            'bill_date' => $billDate,
            'due_date' => fake()->dateTimeBetween($billDate, '+60 days'),
            'received_date' => fake()->optional(0.4)->dateTimeBetween($billDate, 'now'),
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
            'status' => Bill::STATUS_DRAFT,
            'place_of_supply' => fake()->optional(0.3)->stateAbbr(),
            'is_reverse_charge' => false,
            'journal_entry_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'version' => 1,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Bill::STATUS_DRAFT,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Bill::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_PAID,
            'amount_paid' => $attributes['total'],
            'amount_due' => '0.0000',
        ]);
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $total = (float) $attributes['total'];
            $paid = round($total * fake()->randomFloat(2, 0.2, 0.8), 4);
            $due = round($total - $paid, 4);

            return [
                'status' => Bill::STATUS_PARTIAL,
                'amount_paid' => $paid,
                'amount_due' => $due,
            ];
        });
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => Bill::STATUS_VOIDED,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Bill::STATUS_APPROVED,
            'bill_date' => fake()->dateTimeBetween('-90 days', '-45 days'),
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 days'),
        ]);
    }

    public function debitNote(): static
    {
        return $this->state(fn () => [
            'bill_type' => Bill::TYPE_DEBIT_NOTE,
        ]);
    }

    public function creditNote(): static
    {
        return $this->state(fn () => [
            'bill_type' => Bill::TYPE_CREDIT_NOTE,
        ]);
    }

    public function reverseCharge(): static
    {
        return $this->state(fn () => [
            'is_reverse_charge' => true,
        ]);
    }
}

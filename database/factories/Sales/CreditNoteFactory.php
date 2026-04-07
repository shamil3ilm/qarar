<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 10000);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'credit_note_number' => 'CN-' . fake()->unique()->numerify('######'),
            'credit_note_type' => fake()->randomElement([CreditNote::TYPE_SALES, CreditNote::TYPE_PURCHASE]),
            'contact_id' => Contact::factory(),
            'contact_name' => fake()->company(),
            'credit_note_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.000000',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'base_total' => $total,
            'applied_amount' => '0.00',
            'available_amount' => $total,
            'reason' => fake()->sentence(),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => CreditNote::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => CreditNote::STATUS_DRAFT,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => CreditNote::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CreditNote::STATUS_APPLIED,
            'applied_amount' => $attributes['total'] ?? '1000.00',
            'available_amount' => '0.00',
            'approved_at' => now()->subDay(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => CreditNote::STATUS_VOIDED,
        ]);
    }

    public function salesType(): static
    {
        return $this->state(fn () => [
            'credit_note_type' => CreditNote::TYPE_SALES,
        ]);
    }

    public function purchaseType(): static
    {
        return $this->state(fn () => [
            'credit_note_type' => CreditNote::TYPE_PURCHASE,
        ]);
    }
}

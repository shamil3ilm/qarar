<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesReturnFactory extends Factory
{
    protected $model = SalesReturn::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 10000);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $restockingFee = fake()->randomElement([0, round($subtotal * 0.05, 2), round($subtotal * 0.10, 2)]);
        $total = round($subtotal + $taxAmount - $restockingFee, 2);

        return [
            'organization_id' => Organization::factory(),
            'created_by' => \App\Models\User::factory(),
            'branch_id' => null,
            'return_number' => 'RET-' . fake()->unique()->numerify('######'),
            'customer_id' => \App\Models\Sales\Contact::factory(),
            'return_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'reason_notes' => fake()->sentence(),
            'return_type' => fake()->randomElement([
                SalesReturn::TYPE_REFUND,
                SalesReturn::TYPE_EXCHANGE,
                SalesReturn::TYPE_CREDIT_NOTE,
                SalesReturn::TYPE_REPLACEMENT,
            ]),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'restocking_fee' => $restockingFee,
            'total' => $total,
            'refund_amount' => '0.00',
            'status' => SalesReturn::STATUS_PENDING,
            'inspection_status' => SalesReturn::INSPECTION_PENDING,
            'restock_items' => true,
            'items_received' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => SalesReturn::STATUS_PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => SalesReturn::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => SalesReturn::STATUS_RECEIVED,
            'items_received' => true,
            'received_at' => now(),
            'approved_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesReturn::STATUS_COMPLETED,
            'items_received' => true,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDays(2),
            'refund_amount' => $attributes['total'] ?? '1000.00',
            'resolution_type' => SalesReturn::RESOLUTION_FULL_REFUND,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => SalesReturn::STATUS_REJECTED,
            'rejection_reason' => fake()->sentence(),
            'rejected_at' => now(),
            'resolution_type' => SalesReturn::RESOLUTION_REJECTED,
        ]);
    }

    public function refundType(): static
    {
        return $this->state(fn () => [
            'return_type' => SalesReturn::TYPE_REFUND,
        ]);
    }

    public function exchangeType(): static
    {
        return $this->state(fn () => [
            'return_type' => SalesReturn::TYPE_EXCHANGE,
        ]);
    }
}

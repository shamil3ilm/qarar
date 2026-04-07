<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\BulkSaleBatch;
use App\Models\Sales\BulkSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulkSaleItemFactory extends Factory
{
    protected $model = BulkSaleItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 50);
        $unitPrice = fake()->randomFloat(4, 10, 2000);
        $discountAmount = fake()->randomFloat(2, 0, round($quantity * $unitPrice * 0.1, 2));
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $subtotal = round($quantity * $unitPrice - $discountAmount, 2);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'batch_id' => BulkSaleBatch::factory(),
            'line_number' => fake()->numberBetween(1, 100),
            'customer_id' => null,
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_tax_number' => fake()->optional(0.5)->numerify('###############'),
            'product_id' => null,
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_status' => BulkSaleItem::PAYMENT_UNPAID,
            'amount_paid' => '0.00',
            'payment_reference' => null,
            'status' => BulkSaleItem::STATUS_PENDING,
            'invoice_id' => null,
            'payment_id' => null,
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => BulkSaleItem::STATUS_PENDING,
            'processed_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => BulkSaleItem::STATUS_PROCESSING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkSaleItem::STATUS_COMPLETED,
            'processed_at' => now(),
            'payment_status' => BulkSaleItem::PAYMENT_PAID,
            'amount_paid' => $attributes['total_amount'] ?? '100.00',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BulkSaleItem::STATUS_FAILED,
            'processed_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => BulkSaleItem::PAYMENT_PAID,
            'amount_paid' => $attributes['total_amount'] ?? '100.00',
            'payment_reference' => fake()->bothify('PAY-####??'),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'payment_status' => BulkSaleItem::PAYMENT_UNPAID,
            'amount_paid' => '0.00',
        ]);
    }
}

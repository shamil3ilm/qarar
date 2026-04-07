<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesReturnItemFactory extends Factory
{
    protected $model = SalesReturnItem::class;

    public function definition(): array
    {
        $quantityReturned = fake()->randomFloat(4, 1, 20);
        $unitPrice = fake()->randomFloat(4, 10, 2000);
        $subtotal = round($quantityReturned * $unitPrice, 2);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'sales_return_id' => fake()->randomNumber(3, true),
            'description' => fake()->sentence(4),
            'quantity_returned' => $quantityReturned,
            'quantity_received' => '0.0000',
            'quantity_restocked' => '0.0000',
            'quantity_damaged' => '0.0000',
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
            'condition' => fake()->randomElement([
                SalesReturnItem::CONDITION_NEW,
                SalesReturnItem::CONDITION_LIKE_NEW,
                SalesReturnItem::CONDITION_USED,
                SalesReturnItem::CONDITION_DAMAGED,
                SalesReturnItem::CONDITION_DEFECTIVE,
            ]),
            'condition_notes' => fake()->optional(0.4)->sentence(),
            'item_status' => SalesReturnItem::STATUS_PENDING,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_status' => SalesReturnItem::STATUS_RECEIVED,
            'quantity_received' => $attributes['quantity_returned'] ?? '1.0000',
        ]);
    }

    public function inspected(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_status' => SalesReturnItem::STATUS_INSPECTED,
            'quantity_received' => $attributes['quantity_returned'] ?? '1.0000',
        ]);
    }

    public function restocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_status' => SalesReturnItem::STATUS_RESTOCKED,
            'quantity_received' => $attributes['quantity_returned'] ?? '1.0000',
            'quantity_restocked' => $attributes['quantity_returned'] ?? '1.0000',
            'condition' => SalesReturnItem::CONDITION_NEW,
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => SalesReturnItem::CONDITION_DAMAGED,
            'quantity_damaged' => $attributes['quantity_returned'] ?? '1.0000',
            'item_status' => SalesReturnItem::STATUS_DISPOSED,
        ]);
    }
}

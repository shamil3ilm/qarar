<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CreditNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteItemFactory extends Factory
{
    protected $model = CreditNoteItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 50);
        $unitPrice = fake()->randomFloat(4, 10, 2000);
        $subtotal = round($quantity * $unitPrice, 2);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'credit_note_id' => fake()->randomNumber(3, true),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => '0.00',
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
        ];
    }
}

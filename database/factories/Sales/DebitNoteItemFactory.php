<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\DebitNoteItem;
use App\Models\Sales\DebitNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitNoteItemFactory extends Factory
{
    protected $model = DebitNoteItem::class;

    public function definition(): array
    {
        return [
            'debit_note_id' => DebitNote::factory(),
            'product_id' => null,
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 10, 5000),
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'tax_amount' => fake()->randomFloat(2, 0, 500),
            'subtotal' => fake()->randomFloat(2, 10, 50000),
            'total' => fake()->randomFloat(2, 10, 55000),
            'account_id' => null,
        ];
    }
}

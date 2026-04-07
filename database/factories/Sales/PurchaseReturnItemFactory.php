<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PurchaseReturnItem;
use App\Models\Sales\PurchaseReturn;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseReturnItemFactory extends Factory
{
    protected $model = PurchaseReturnItem::class;

    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'product_id' => Product::factory(),
            'bill_item_id' => null,
            'variant_id' => null,
            'batch_id' => null,
            'description' => fake()->sentence(),
            'quantity_returned' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 10, 5000),
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'tax_amount' => fake()->randomFloat(2, 0, 500),
            'subtotal' => fake()->randomFloat(2, 10, 50000),
            'total' => fake()->randomFloat(2, 10, 55000),
            'condition' => fake()->randomElement(['new', 'used', 'damaged', 'defective']),
            'condition_notes' => fake()->optional(0.3)->sentence(),
            'item_status' => fake()->randomElement(['pending', 'shipped', 'received']),
        ];
    }
}

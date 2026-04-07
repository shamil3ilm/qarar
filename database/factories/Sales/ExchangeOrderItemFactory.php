<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ExchangeOrderItem;
use App\Models\Sales\ExchangeOrder;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeOrderItemFactory extends Factory
{
    protected $model = ExchangeOrderItem::class;

    public function definition(): array
    {
        return [
            'exchange_order_id' => ExchangeOrder::factory(),
            'original_product_id' => Product::factory(),
            'replacement_product_id' => Product::factory(),
            'replacement_variant_id' => null,
            'original_quantity' => fake()->randomFloat(4, 1, 10),
            'replacement_quantity' => fake()->randomFloat(4, 1, 10),
            'original_unit_price' => fake()->randomFloat(4, 10, 5000),
            'replacement_unit_price' => fake()->randomFloat(4, 10, 5000),
            'price_difference' => fake()->randomFloat(2, -1000, 1000),
        ];
    }
}

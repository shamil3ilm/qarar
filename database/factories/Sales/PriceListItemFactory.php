<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PriceListItem;
use App\Models\Sales\PriceList;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListItemFactory extends Factory
{
    protected $model = PriceListItem::class;

    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'product_id' => Product::factory(),
            'unit_price' => fake()->randomFloat(4, 1, 10000),
            'min_quantity' => fake()->optional(0.3)->randomFloat(4, 1, 10),
            'max_quantity' => fake()->optional(0.3)->randomFloat(4, 11, 1000),
            'discount_percent' => fake()->optional(0.3)->randomFloat(2, 1, 25),
            'valid_from' => null,
            'valid_until' => null,
        ];
    }
}

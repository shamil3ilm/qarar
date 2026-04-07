<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductPriceHistory;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceHistoryFactory extends Factory
{
    protected $model = ProductPriceHistory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'price_type' => fake()->randomElement(['selling', 'purchase', 'cost']),
            'old_price' => fake()->randomFloat(4, 10, 5000),
            'new_price' => fake()->randomFloat(4, 10, 5000),
            'change_percent' => fake()->randomFloat(2, -50, 50),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'reason' => fake()->optional(0.5)->sentence(),
            'effective_from' => fake()->dateTimeBetween('-6 months', 'now'),
            'effective_to' => null,
            'changed_by' => null,
        ];
    }
}

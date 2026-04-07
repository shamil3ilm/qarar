<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockAdjustmentLine;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentLineFactory extends Factory
{
    protected $model = StockAdjustmentLine::class;

    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'location_id' => null,
            'system_quantity' => fake()->randomFloat(4, 0, 1000),
            'actual_quantity' => fake()->randomFloat(4, 0, 1000),
            'difference' => fake()->randomFloat(4, -100, 100),
            'unit_cost' => fake()->randomFloat(4, 1, 1000),
            'total_cost' => fake()->randomFloat(4, 1, 10000),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

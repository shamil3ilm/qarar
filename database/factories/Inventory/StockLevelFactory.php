<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockLevel;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'location_id' => null,
            'quantity' => fake()->randomFloat(4, 0, 10000),
            'reserved_quantity' => fake()->randomFloat(4, 0, 100),
            'average_cost' => fake()->randomFloat(4, 1, 5000),
            'last_purchase_price' => fake()->randomFloat(4, 1, 5000),
            'total_value' => fake()->randomFloat(4, 100, 500000),
            'reorder_level' => fake()->optional(0.5)->randomFloat(4, 5, 50),
            'reorder_quantity' => fake()->optional(0.5)->randomFloat(4, 10, 100),
            'maximum_stock' => fake()->optional(0.3)->randomFloat(4, 100, 10000),
            'last_count_date' => fake()->optional(0.5)->dateTimeBetween('-3 months', 'now'),
        ];
    }
}

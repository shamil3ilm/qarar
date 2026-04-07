<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockMovement;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'location_id' => null,
            'movement_type' => fake()->randomElement(['purchase', 'sale', 'adjustment', 'transfer', 'production', 'return']),
            'direction' => fake()->randomElement(['in', 'out']),
            'quantity' => fake()->randomFloat(4, 1, 1000),
            'unit_cost' => fake()->randomFloat(4, 1, 5000),
            'total_cost' => fake()->randomFloat(4, 1, 500000),
            'balance_after' => fake()->randomFloat(4, 0, 10000),
            'reference_type' => null,
            'reference_id' => null,
            'reference_number' => fake()->optional(0.5)->bothify('REF-####'),
            'from_warehouse_id' => null,
            'to_warehouse_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}

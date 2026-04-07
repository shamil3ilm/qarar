<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'batch_number' => 'BATCH-' . fake()->unique()->numerify('######'),
            'lot_number' => fake()->optional(0.5)->bothify('LOT-####'),
            'serial_number' => fake()->optional(0.3)->bothify('SN-########'),
            'manufacturing_date' => fake()->optional(0.5)->dateTimeBetween('-1 year', '-1 month'),
            'expiry_date' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+3 years'),
            'received_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'quantity' => fake()->randomFloat(4, 1, 10000),
            'reserved_quantity' => 0,
            'unit_cost' => fake()->randomFloat(4, 1, 5000),
            'status' => fake()->randomElement(['available', 'reserved', 'expired', 'damaged', 'quarantine', 'depleted']),
            'supplier_id' => null,
            'grn_number' => fake()->optional(0.3)->bothify('GRN-####'),
            'metadata' => null,
        ];
    }
}

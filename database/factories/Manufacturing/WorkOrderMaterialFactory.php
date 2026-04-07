<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\WorkOrderMaterial;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderMaterialFactory extends Factory
{
    protected $model = WorkOrderMaterial::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'bom_line_id' => null,
            'product_id' => Product::factory(),
            'variant_id' => null,
            'description' => fake()->optional(0.5)->sentence(),
            'required_quantity' => fake()->randomFloat(4, 1, 1000),
            'issued_quantity' => 0,
            'consumed_quantity' => 0,
            'returned_quantity' => 0,
            'wastage_quantity' => 0,
            'unit_id' => null,
            'unit_cost' => fake()->randomFloat(4, 1, 5000),
            'total_cost' => fake()->randomFloat(4, 1, 500000),
            'warehouse_id' => null,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }
}

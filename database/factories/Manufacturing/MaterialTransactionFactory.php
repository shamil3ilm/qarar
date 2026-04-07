<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\MaterialTransaction;
use App\Models\Core\Organization;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialTransactionFactory extends Factory
{
    protected $model = MaterialTransaction::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'work_order_id' => WorkOrder::factory(),
            'work_order_material_id' => null,
            'transaction_type' => fake()->randomElement(['issue', 'return', 'consumption']),
            'transaction_datetime' => now(),
            'quantity' => fake()->randomFloat(4, 1, 1000),
            'unit_cost' => fake()->randomFloat(4, 1, 5000),
            'warehouse_id' => null,
            'stock_movement_id' => null,
            'reference' => fake()->optional(0.3)->bothify('MTX-####'),
            'notes' => fake()->optional(0.3)->sentence(),
            'processed_by' => null,
        ];
    }
}

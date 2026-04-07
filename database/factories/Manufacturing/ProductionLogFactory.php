<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\ProductionLog;
use App\Models\Core\Organization;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionLogFactory extends Factory
{
    protected $model = ProductionLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'work_order_id' => WorkOrder::factory(),
            'logged_at' => now(),
            'quantity_produced' => fake()->randomFloat(4, 1, 1000),
            'quantity_rejected' => fake()->randomFloat(4, 0, 50),
            'rejection_reason' => fake()->optional(0.2)->sentence(),
            'quality_checked' => fake()->boolean(50),
            'quality_checked_by' => null,
            'quality_checked_at' => null,
            'quality_parameters' => null,
            'batch_number' => fake()->optional(0.5)->bothify('BATCH-####'),
            'lot_number' => fake()->optional(0.3)->bothify('LOT-####'),
            'expiry_date' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+2 years'),
            'stock_movement_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'logged_by' => null,
        ];
    }
}

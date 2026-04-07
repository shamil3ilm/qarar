<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\WorkOrderOperation;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderOperationFactory extends Factory
{
    protected $model = WorkOrderOperation::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'bom_operation_id' => null,
            'name' => fake()->randomElement(['Cutting', 'Assembly', 'Testing', 'Packaging']),
            'instructions' => fake()->optional(0.3)->paragraph(),
            'sequence' => fake()->numberBetween(1, 10),
            'estimated_minutes' => fake()->numberBetween(10, 480),
            'actual_minutes' => fake()->optional(0.5)->numberBetween(10, 600),
            'started_at' => fake()->optional(0.3)->dateTimeBetween('-1 month', 'now'),
            'completed_at' => null,
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed', 'skipped']),
            'assigned_to' => null,
            'completed_by' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

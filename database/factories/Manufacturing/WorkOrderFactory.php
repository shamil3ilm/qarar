<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        $plannedStartDate = fake()->dateTimeBetween('-7 days', '+14 days');
        $plannedEndDate = (clone $plannedStartDate)->modify('+' . fake()->numberBetween(1, 14) . ' days');
        $plannedQuantity = fake()->randomFloat(4, 10, 1000);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'work_order_number' => strtoupper(fake()->unique()->lexify('WO-####-???')),
            'bom_template_id' => BomTemplate::factory(),
            'sales_order_id' => null,
            'sales_order_line_id' => null,
            'product_id' => Product::factory(),
            'variant_id' => null,
            'planned_quantity' => $plannedQuantity,
            'produced_quantity' => 0,
            'rejected_quantity' => 0,
            'unit_id' => null,
            'planned_start_date' => $plannedStartDate,
            'planned_end_date' => $plannedEndDate,
            'actual_start_datetime' => null,
            'actual_end_datetime' => null,
            'source_warehouse_id' => null,
            'target_warehouse_id' => null,
            'estimated_material_cost' => fake()->randomFloat(4, 500, 50000),
            'estimated_labor_cost' => fake()->randomFloat(4, 200, 10000),
            'estimated_overhead_cost' => fake()->randomFloat(4, 50, 5000),
            'actual_material_cost' => 0,
            'actual_labor_cost' => 0,
            'actual_overhead_cost' => 0,
            'status' => WorkOrder::STATUS_DRAFT,
            'priority' => fake()->randomElement([
                WorkOrder::PRIORITY_LOW,
                WorkOrder::PRIORITY_NORMAL,
                WorkOrder::PRIORITY_HIGH,
                WorkOrder::PRIORITY_URGENT,
            ]),
            'assigned_to' => null,
            'supervisor_id' => null,
            'notes' => null,
            'cancellation_reason' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => WorkOrder::STATUS_DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => WorkOrder::STATUS_PENDING]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => WorkOrder::STATUS_SCHEDULED]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'actual_start_datetime' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $plannedQuantity = $attributes['planned_quantity'] ?? 100;
            $producedQuantity = $plannedQuantity;
            $rejectedQuantity = round($producedQuantity * fake()->randomFloat(2, 0, 0.05), 4);

            return [
                'status' => WorkOrder::STATUS_COMPLETED,
                'produced_quantity' => $producedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'actual_start_datetime' => now()->subDays(3),
                'actual_end_datetime' => now(),
                'actual_material_cost' => fake()->randomFloat(4, 500, 50000),
                'actual_labor_cost' => fake()->randomFloat(4, 200, 10000),
                'actual_overhead_cost' => fake()->randomFloat(4, 50, 5000),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrder::STATUS_CANCELLED,
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => WorkOrder::PRIORITY_HIGH]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => WorkOrder::PRIORITY_URGENT]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_start_date' => fake()->dateTimeBetween('-14 days', '-7 days'),
            'planned_end_date' => fake()->dateTimeBetween('-5 days', '-1 day'),
            'actual_start_datetime' => fake()->dateTimeBetween('-14 days', '-7 days'),
        ]);
    }
}

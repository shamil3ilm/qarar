<?php

declare(strict_types=1);

namespace Database\Factories\Maintenance;

use App\Models\Core\Organization;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceOrder>
 */
class MaintenanceOrderFactory extends Factory
{
    protected $model = MaintenanceOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id'    => Equipment::factory(),
            'order_number'    => strtoupper(fake()->unique()->lexify('MO-###-???')),
            'order_type'      => fake()->randomElement(MaintenanceOrder::ORDER_TYPES),
            'priority'        => fake()->randomElement(MaintenanceOrder::PRIORITIES),
            'status'          => MaintenanceOrder::STATUS_OPEN,
            'description'     => fake()->sentence(),
            'scheduled_start' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'scheduled_end'   => now()->addDays(2)->format('Y-m-d H:i:s'),
            'estimated_cost'  => fake()->optional(0.6)->randomFloat(2, 100, 5000),
            'created_by'      => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => MaintenanceOrder::STATUS_OPEN]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status'       => MaintenanceOrder::STATUS_IN_PROGRESS,
            'actual_start' => now()->subHour()->format('Y-m-d H:i:s'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => MaintenanceOrder::STATUS_COMPLETED,
            'actual_start' => now()->subHours(4)->format('Y-m-d H:i:s'),
            'actual_end'   => now()->subHour()->format('Y-m-d H:i:s'),
        ]);
    }

    public function corrective(): static
    {
        return $this->state(fn () => ['order_type' => MaintenanceOrder::TYPE_CORRECTIVE]);
    }

    public function preventive(): static
    {
        return $this->state(fn () => ['order_type' => MaintenanceOrder::TYPE_PREVENTIVE]);
    }
}

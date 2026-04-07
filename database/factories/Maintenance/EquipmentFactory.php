<?php

declare(strict_types=1);

namespace Database\Factories\Maintenance;

use App\Models\Core\Organization;
use App\Models\Maintenance\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'organization_id'  => Organization::factory(),
            'equipment_number' => strtoupper(fake()->unique()->lexify('EQ-???-###')),
            'name'             => fake()->words(2, true) . ' Machine',
            'description'      => fake()->optional(0.5)->sentence(),
            'manufacturer'     => fake()->optional(0.6)->company(),
            'model'            => fake()->optional(0.5)->lexify('MOD-???-###'),
            'serial_number'    => fake()->optional(0.5)->unique()->numerify('SN########'),
            'acquisition_date' => fake()->optional(0.7)->dateTimeBetween('-5 years', '-1 month')?->format('Y-m-d'),
            'acquisition_cost' => fake()->optional(0.7)->randomFloat(2, 1000, 50000),
            'warranty_expiry'  => fake()->optional(0.3)->dateTimeBetween('now', '+3 years')?->format('Y-m-d'),
            'status'           => Equipment::STATUS_ACTIVE,
            'created_by'       => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Equipment::STATUS_ACTIVE]);
    }

    public function underMaintenance(): static
    {
        return $this->state(fn () => ['status' => Equipment::STATUS_UNDER_MAINTENANCE]);
    }

    public function decommissioned(): static
    {
        return $this->state(fn () => ['status' => Equipment::STATUS_DECOMMISSIONED]);
    }
}

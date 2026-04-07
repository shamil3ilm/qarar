<?php

declare(strict_types=1);

namespace Database\Factories\Maintenance;

use App\Models\Core\Organization;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        return [
            'organization_id'          => Organization::factory(),
            'equipment_id'             => Equipment::factory(),
            'name'                     => fake()->words(3, true) . ' Plan',
            'maintenance_type'         => fake()->randomElement(MaintenancePlan::MAINTENANCE_TYPES),
            'frequency_type'           => fake()->randomElement(MaintenancePlan::FREQUENCY_TYPES),
            'frequency_value'          => fake()->numberBetween(1, 12),
            'estimated_duration_hours' => fake()->optional(0.7)->randomFloat(2, 1, 8),
            'description'              => fake()->optional(0.5)->sentence(),
            'is_active'                => true,
            'created_by'               => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function preventive(): static
    {
        return $this->state(fn () => [
            'maintenance_type' => MaintenancePlan::TYPE_PREVENTIVE,
            'frequency_type'   => MaintenancePlan::FREQ_MONTHLY,
            'frequency_value'  => 1,
        ]);
    }
}

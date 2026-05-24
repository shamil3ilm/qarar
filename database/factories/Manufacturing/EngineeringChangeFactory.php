<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\EngineeringChange;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngineeringChangeFactory extends Factory
{
    protected $model = EngineeringChange::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'change_number'   => strtoupper(fake()->unique()->bothify('ECR-####-??')),
            'change_type'     => fake()->randomElement(['bom_change', 'routing_change', 'product_spec_change', 'drawing_change']),
            'description'     => fake()->sentence(),
            'reason'          => fake()->sentence(),
            'status'          => EngineeringChange::STATUS_DRAFT,
            'effectivity_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'priority'        => EngineeringChange::PRIORITY_NORMAL,
            'requested_by'    => null,
            'approved_by'     => null,
            'approved_at'     => null,
            'implemented_at'  => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EngineeringChange::STATUS_DRAFT]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => EngineeringChange::STATUS_SUBMITTED]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => EngineeringChange::STATUS_APPROVED]);
    }
}

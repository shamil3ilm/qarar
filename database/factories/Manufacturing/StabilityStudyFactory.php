<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\StabilityStudy;
use Illuminate\Database\Eloquent\Factories\Factory;

class StabilityStudyFactory extends Factory
{
    protected $model = StabilityStudy::class;

    public function definition(): array
    {
        return [
            'organization_id'    => Organization::factory(),
            'study_number'       => strtoupper(fake()->unique()->bothify('SS-####-??')),
            'product_id'         => Product::factory(),
            'inventory_batch_id' => null,
            'study_type'         => fake()->randomElement(['real_time', 'accelerated', 'intermediate']),
            'status'             => StabilityStudy::STATUS_PLANNED,
            'start_date'         => now()->format('Y-m-d'),
            'planned_end_date'   => now()->addYear()->format('Y-m-d'),
            'storage_condition'  => '25°C / 60% RH',
            'protocol_reference' => null,
            'notes'              => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => StabilityStudy::STATUS_ACTIVE]);
    }
}

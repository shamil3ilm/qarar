<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\SkipLotSamplingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkipLotSamplingPlanFactory extends Factory
{
    protected $model = SkipLotSamplingPlan::class;

    public function definition(): array
    {
        return [
            'organization_id'                    => Organization::factory(),
            'plan_code'                          => strtoupper(fake()->unique()->bothify('SLP-???')),
            'plan_name'                          => fake()->words(3, true),
            'plan_type'                          => fake()->randomElement(['skip_lot', 'reduced', 'normal', 'tightened']),
            'inspection_frequency'               => fake()->numberBetween(1, 10),
            'sample_size_percent'                => fake()->randomFloat(2, 5, 100),
            'accept_number'                      => 0,
            'reject_number'                      => 1,
            'switch_rule_reduced_to_normal'      => null,
            'switch_rule_normal_to_tightened'    => null,
            'switch_rule_tightened_to_rejected'  => null,
            'is_active'                          => true,
        ];
    }
}

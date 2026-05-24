<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\QualityCostEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityCostEntryFactory extends Factory
{
    protected $model = QualityCostEntry::class;

    public function definition(): array
    {
        return [
            'organization_id'   => Organization::factory(),
            'cost_category'     => fake()->randomElement(['prevention', 'appraisal', 'internal_failure', 'external_failure']),
            'cost_subcategory'  => null,
            'reference_type'    => null,
            'reference_id'      => null,
            'product_id'        => null,
            'period'            => fake()->numberBetween(1, 12),
            'fiscal_year'       => 2026,
            'amount'            => fake()->randomFloat(4, 100, 50000),
            'description'       => fake()->sentence(),
            'recorded_by'       => null,
        ];
    }
}

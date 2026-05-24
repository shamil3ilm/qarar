<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithholdingTaxCode>
 */
class WithholdingTaxCodeFactory extends Factory
{
    protected $model = WithholdingTaxCode::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code'            => strtoupper(fake()->unique()->lexify('WHT-??')),
            'name'            => fake()->words(3, true),
            'applicable_to'   => WithholdingTaxCode::APPLICABLE_SUPPLIER,
            'rate'            => fake()->randomElement([5.0, 10.0, 15.0]),
            'country_code'    => fake()->randomElement(['SA', 'AE', 'OM', 'KW', 'BH', 'QA']),
            'tax_type'        => 'cross_border_wht',
            'is_active'       => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\SocialInsuranceScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialInsuranceSchemeFactory extends Factory
{
    protected $model = SocialInsuranceScheme::class;

    public function definition(): array
    {
        return [
            'organization_id'            => Organization::factory(),
            'name'                       => fake()->company() . ' Social Insurance',
            'country_code'               => fake()->randomElement(['SA', 'AE', 'OM', 'KW', 'BH', 'QA']),
            'scheme_code'                => strtoupper(fake()->unique()->bothify('???_###')),
            'employee_contribution_pct'  => '9.00',
            'employer_contribution_pct'  => '9.00',
            'work_hazard_pct'            => '2.00',
            'applicable_to'             => 'nationals_only',
            'salary_ceiling'            => '45000.0000',
            'salary_floor'              => '400.0000',
            'is_active'                 => true,
        ];
    }
}

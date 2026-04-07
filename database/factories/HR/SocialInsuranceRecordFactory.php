<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialInsuranceRecordFactory extends Factory
{
    protected $model = SocialInsuranceRecord::class;

    public function definition(): array
    {
        return [
            'organization_id'   => Organization::factory(),
            'employee_id'       => Employee::factory(),
            'scheme_id'         => SocialInsuranceSchemeFactory::new(),
            'employee_number_si' => 'SI-' . fake()->numerify('######'),
            'enrollment_date'   => fake()->dateTimeBetween('-5 years', '-1 month'),
            'termination_date'  => null,
            'status'            => SocialInsuranceRecord::STATUS_ACTIVE,
            'insurable_salary'  => number_format(fake()->randomFloat(2, 1000, 30000), 4, '.', ''),
        ];
    }
}

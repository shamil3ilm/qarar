<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\SocialInsuranceScheme;
use App\Models\HR\SocialInsuranceSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialInsuranceSubmissionFactory extends Factory
{
    protected $model = SocialInsuranceSubmission::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'scheme_id'       => SocialInsuranceSchemeFactory::new(),
            'period_year'     => fake()->numberBetween(2024, 2026),
            'period_month'    => fake()->numberBetween(1, 12),
            'total_employees'          => 0,
            'total_insurable_salary'   => '0.0000',
            'total_employee_contrib'   => '0.0000',
            'total_employer_contrib'   => '0.0000',
            'total_work_hazard_contrib' => '0.0000',
            'total_amount'             => '0.0000',
            'status'          => SocialInsuranceSubmission::STATUS_DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => SocialInsuranceSubmission::STATUS_SUBMITTED]);
    }
}

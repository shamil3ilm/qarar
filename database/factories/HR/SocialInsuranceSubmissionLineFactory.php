<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceSubmission;
use App\Models\HR\SocialInsuranceSubmissionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialInsuranceSubmissionLineFactory extends Factory
{
    protected $model = SocialInsuranceSubmissionLine::class;

    public function definition(): array
    {
        $insurable = fake()->randomFloat(3, 1000, 30000);
        $empPct    = 9.0;
        $empContrib = round($insurable * $empPct / 100, 3);
        $emplrContrib = round($insurable * 9.0 / 100, 3);
        $hazard = round($insurable * 2.0 / 100, 3);

        return [
            'submission_id'          => SocialInsuranceSubmission::factory(),
            'employee_id'            => Employee::factory(),
            'record_id'              => SocialInsuranceRecord::factory(),
            'employee_number_si'     => 'SI-' . fake()->numerify('######'),
            'insurable_salary'       => number_format($insurable, 4, '.', ''),
            'employee_contribution'  => number_format($empContrib, 4, '.', ''),
            'employer_contribution'  => number_format($emplrContrib, 4, '.', ''),
            'work_hazard_contribution' => number_format($hazard, 4, '.', ''),
            'total_contribution'     => number_format($empContrib + $emplrContrib + $hazard, 4, '.', ''),
        ];
    }
}

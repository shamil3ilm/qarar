<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\ZakatAssessment;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZakatAssessment>
 */
class ZakatAssessmentFactory extends Factory
{
    protected $model = ZakatAssessment::class;

    public function definition(): array
    {
        $zakatBase = fake()->randomFloat(2, 100000, 10000000);
        $zakatDue  = round($zakatBase * 0.025, 4);

        return [
            'organization_id'      => Organization::factory(),
            'assessment_year'      => fake()->year(),
            'total_assets'         => $zakatBase * 2,
            'total_liabilities'    => $zakatBase * 0.5,
            'non_zakatable_assets' => $zakatBase * 0.5,
            'zakat_base'           => $zakatBase,
            'zakat_rate'           => 2.5,
            'zakat_due'            => $zakatDue,
            'saudi_ownership_pct'  => 100.0,
            'zakat_paid'           => 0,
            'zakat_remaining'      => $zakatDue,
            'status'               => ZakatAssessment::STATUS_DRAFT,
        ];
    }
}

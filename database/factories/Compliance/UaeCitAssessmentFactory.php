<?php

declare(strict_types=1);

namespace Database\Factories\Compliance;

use App\Models\Compliance\UaeCitAssessment;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UaeCitAssessmentFactory extends Factory
{
    protected $model = UaeCitAssessment::class;

    public function definition(): array
    {
        $accounting = $this->faker->randomFloat(2, 100_000, 2_000_000);
        $addBacks   = $this->faker->randomFloat(2, 0, 50_000);
        $deductions = $this->faker->randomFloat(2, 0, 30_000);
        $taxable    = max(0.0, $accounting + $addBacks - $deductions);
        $due        = round(max(0.0, $taxable - 375_000) * 0.09, 4);

        return [
            'organization_id'    => Organization::factory(),
            'tax_year'           => 2025,
            'accounting_income'  => $accounting,
            'add_backs'          => $addBacks,
            'deductions'         => $deductions,
            'taxable_income'     => $taxable,
            'zero_rate_threshold' => 375_000.0,
            'small_business_threshold' => 3_000_000.0,
            'cit_rate'           => 9.0,
            'small_business_relief' => false,
            'cit_due'            => $due,
            'cit_paid'           => 0.0,
            'cit_remaining'      => $due,
            'status'             => UaeCitAssessment::STATUS_DRAFT,
            'filing_due_date'    => '2026-09-30',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => UaeCitAssessment::STATUS_SUBMITTED]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'        => UaeCitAssessment::STATUS_PAID,
            'cit_paid'      => $attrs['cit_due'] ?? 0,
            'cit_remaining' => 0.0,
        ]);
    }
}

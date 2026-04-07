<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseReport;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseReportFactory extends Factory
{
    protected $model = ExpenseReport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => null,
            'report_number' => 'ER-' . fake()->unique()->numerify('######'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.5)->sentence(),
            'period_start' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'period_end' => fake()->dateTimeBetween('-1 month', 'now'),
            'total_amount' => fake()->randomFloat(2, 100, 50000),
            'approved_amount' => fake()->randomFloat(2, 0, 50000),
            'reimbursed_amount' => 0,
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'paid', 'rejected']),
            'approved_by' => null,
            'approved_at' => null,
            'paid_at' => null,
            'rejection_reason' => null,
        ];
    }
}

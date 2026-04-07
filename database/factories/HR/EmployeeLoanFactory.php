<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\EmployeeLoan;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeLoanFactory extends Factory
{
    protected $model = EmployeeLoan::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'loan_number' => 'EL-' . fake()->unique()->numerify('######'),
            'loan_type' => fake()->randomElement(['personal', 'advance', 'emergency']),
            'principal_amount' => fake()->randomFloat(2, 1000, 50000),
            'interest_rate' => fake()->randomFloat(2, 0, 10),
            'disbursement_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'repayment_start_date' => fake()->dateTimeBetween('-1 year', '+1 month'),
            'tenure_months' => fake()->numberBetween(3, 24),
            'emi_amount' => fake()->randomFloat(2, 100, 5000),
            'total_repaid' => fake()->randomFloat(2, 0, 30000),
            'balance' => fake()->randomFloat(2, 0, 50000),
            'status' => fake()->randomElement(['pending', 'active', 'completed', 'rejected']),
            'approved_by' => null,
            'approved_at' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

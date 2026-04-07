<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\LoanRepayment;
use App\Models\HR\EmployeeLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanRepaymentFactory extends Factory
{
    protected $model = LoanRepayment::class;

    public function definition(): array
    {
        return [
            'employee_loan_id' => EmployeeLoan::factory(),
            'payslip_id' => null,
            'installment_number' => fake()->numberBetween(1, 24),
            'due_date' => fake()->dateTimeBetween('now', '+2 years'),
            'principal_amount' => fake()->randomFloat(4, 100, 5000),
            'interest_amount' => fake()->randomFloat(4, 0, 500),
            'total_amount' => fake()->randomFloat(4, 100, 5500),
            'amount_paid' => 0,
            'paid_date' => null,
            'status' => fake()->randomElement(['pending', 'paid', 'overdue']),
        ];
    }
}

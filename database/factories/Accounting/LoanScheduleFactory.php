<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\LoanSchedule;
use App\Models\Accounting\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanScheduleFactory extends Factory
{
    protected $model = LoanSchedule::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'installment_number' => fake()->numberBetween(1, 60),
            'due_date' => fake()->dateTimeBetween('now', '+5 years'),
            'principal_amount' => fake()->randomFloat(2, 100, 10000),
            'interest_amount' => fake()->randomFloat(2, 10, 2000),
            'total_amount' => fake()->randomFloat(2, 110, 12000),
            'outstanding_balance' => fake()->randomFloat(2, 0, 500000),
            'status' => fake()->randomElement(['pending', 'paid', 'overdue', 'partially_paid']),
            'paid_amount' => fake()->randomFloat(2, 0, 12000),
            'paid_date' => null,
        ];
    }
}
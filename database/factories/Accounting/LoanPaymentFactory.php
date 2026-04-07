<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\LoanPayment;
use App\Models\Accounting\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanPaymentFactory extends Factory
{
    protected $model = LoanPayment::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'schedule_id' => null,
            'payment_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'principal_paid' => fake()->randomFloat(2, 100, 10000),
            'interest_paid' => fake()->randomFloat(2, 10, 2000),
            'penalty_paid' => fake()->randomFloat(2, 0, 100),
            'total_paid' => fake()->randomFloat(2, 110, 12000),
            'payment_method' => fake()->randomElement(['bank_transfer', 'cheque', 'cash']),
            'reference' => fake()->optional(0.5)->bothify('PMT-####'),
            'journal_entry_id' => null,
            'payroll_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'received_by' => null,
        ];
    }
}
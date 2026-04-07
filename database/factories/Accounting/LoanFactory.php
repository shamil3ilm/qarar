<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Loan;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'loan_number' => 'LOAN-' . fake()->unique()->numerify('######'),
            'loan_type' => fake()->randomElement(['given', 'taken']),
            'loan_category' => fake()->randomElement(['employee', 'personal', 'business', 'mortgage']),
            'employee_id' => null,
            'contact_id' => null,
            'borrower_name' => fake()->name(),
            'lender_type' => fake()->randomElement(['bank', 'individual', 'company']),
            'lender_name' => fake()->company(),
            'lender_contact_id' => null,
            'principal_amount' => fake()->randomFloat(2, 5000, 500000),
            'interest_rate' => fake()->randomFloat(2, 1, 15),
            'interest_type' => fake()->randomElement(['simple', 'compound', 'flat']),
            'total_interest' => fake()->randomFloat(2, 500, 50000),
            'total_amount' => fake()->randomFloat(2, 5500, 550000),
            'outstanding_amount' => fake()->randomFloat(2, 0, 500000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'disbursement_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'first_payment_date' => fake()->dateTimeBetween('-2 years', '+1 month'),
            'maturity_date' => fake()->dateTimeBetween('+1 year', '+5 years'),
            'tenure_months' => fake()->numberBetween(6, 60),
            'payment_frequency' => fake()->randomElement(['monthly', 'quarterly']),
            'emi_amount' => fake()->randomFloat(2, 500, 20000),
            'total_installments' => fake()->numberBetween(6, 60),
            'paid_installments' => fake()->numberBetween(0, 30),
            'status' => fake()->randomElement(['draft', 'active', 'closed', 'defaulted']),
            'approval_status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'loan_account_id' => null,
            'interest_account_id' => null,
        ];
    }
}
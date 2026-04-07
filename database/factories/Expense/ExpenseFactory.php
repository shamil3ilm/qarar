<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\Expense;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'expense_number' => 'EXP-' . fake()->unique()->numerify('######'),
            'category_id' => null,
            'employee_id' => null,
            'supplier_id' => null,
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'due_date' => fake()->optional(0.5)->dateTimeBetween('now', '+1 month'),
            'payment_method' => fake()->randomElement(['cash', 'card', 'bank_transfer']),
            'reference' => fake()->optional(0.4)->bothify('REF-####'),
            'description' => fake()->sentence(),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'exchange_rate' => '1.000000',
            'amount' => fake()->randomFloat(2, 10, 10000),
            'tax_amount' => fake()->randomFloat(2, 0, 1500),
            'total_amount' => fake()->randomFloat(2, 10, 11500),
            'base_amount' => fake()->randomFloat(2, 10, 11500),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'paid', 'rejected']),
            'is_reimbursable' => fake()->boolean(30),
            'is_recurring' => false,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationFactory extends Factory
{
    protected $model = BankReconciliation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bank_account_id' => BankAccount::factory(),
            'statement_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'statement_balance' => fake()->randomFloat(2, 1000, 500000),
            'book_balance' => fake()->randomFloat(2, 1000, 500000),
            'adjusted_book_balance' => fake()->randomFloat(2, 1000, 500000),
            'difference' => fake()->randomFloat(2, -1000, 1000),
            'status' => fake()->randomElement(['in_progress', 'completed', 'cancelled']),
            'summary' => [],
            'created_by' => null,
            'completed_by' => null,
            'completed_at' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
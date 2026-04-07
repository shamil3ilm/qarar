<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reference' => fake()->bothify('TXN-####??'),
            'description' => fake()->sentence(),
            'debit' => fake()->randomFloat(4, 0, 10000),
            'credit' => fake()->randomFloat(4, 0, 10000),
            'running_balance' => fake()->randomFloat(4, 1000, 500000),
            'is_reconciled' => false,
            'reconciled_date' => null,
            'journal_entry_id' => null,
            'journal_line_id' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }
}
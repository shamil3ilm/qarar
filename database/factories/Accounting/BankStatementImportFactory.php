<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\BankAccount;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankStatementImportFactory extends Factory
{
    protected $model = BankStatementImport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bank_account_id' => BankAccount::factory(),
            'user_id' => null,
            'file_name' => fake()->slug() . '.csv',
            'file_path' => 'imports/' . fake()->slug() . '.csv',
            'file_type' => fake()->randomElement(['csv', 'ofx', 'qif']),
            'statement_start_date' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'statement_end_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'total_transactions' => fake()->numberBetween(10, 500),
            'imported_transactions' => fake()->numberBetween(10, 500),
            'duplicate_transactions' => fake()->numberBetween(0, 10),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'errors' => null,
        ];
    }
}
<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountOpeningBalance;
use App\Models\Accounting\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountOpeningBalanceFactory extends Factory
{
    protected $model = AccountOpeningBalance::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'debit' => fake()->randomFloat(4, 0, 50000),
            'credit' => fake()->randomFloat(4, 0, 50000),
        ];
    }
}

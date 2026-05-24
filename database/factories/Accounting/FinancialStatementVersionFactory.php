<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialStatementVersion;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialStatementVersionFactory extends Factory
{
    protected $model = FinancialStatementVersion::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Statement',
            'description' => fake()->optional(0.4)->sentence(),
            'type' => fake()->randomElement([
                FinancialStatementVersion::TYPE_BALANCE_SHEET,
                FinancialStatementVersion::TYPE_INCOME_STATEMENT,
                FinancialStatementVersion::TYPE_CASH_FLOW,
            ]),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

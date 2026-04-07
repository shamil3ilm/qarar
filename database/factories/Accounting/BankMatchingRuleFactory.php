<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankMatchingRule;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankMatchingRuleFactory extends Factory
{
    protected $model = BankMatchingRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bank_account_id' => BankAccount::factory(),
            'name' => fake()->words(3, true) . ' Rule',
            'match_field' => fake()->randomElement(['description', 'reference', 'amount']),
            'match_type' => fake()->randomElement(['contains', 'starts_with', 'equals']),
            'match_value' => fake()->word(),
            'transaction_type' => fake()->optional(0.5)->randomElement(['debit', 'credit']),
            'action' => fake()->randomElement(['categorize', 'match', 'ignore']),
            'action_data' => ['account_id' => fake()->numberBetween(1, 100)],
            'priority' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}

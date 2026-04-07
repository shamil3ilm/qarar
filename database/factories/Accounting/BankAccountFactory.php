<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'bank_name' => fake()->company() . ' Bank',
            'account_name' => fake()->company() . ' Operating Account',
            'account_number' => fake()->numerify('##########'),
            'iban' => fake()->iban('SA'),
            'swift_code' => strtoupper(fake()->lexify('????????')),
            'branch_name' => fake()->city() . ' Branch',
            'branch_code' => fake()->numerify('####'),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'account_type' => fake()->randomElement(['checking', 'savings', 'current']),
            'gl_account_id' => null,
            'current_balance' => fake()->randomFloat(4, 1000, 500000),
            'last_reconciled_date' => fake()->optional(0.5)->dateTimeBetween('-3 months', 'now'),
            'last_reconciled_balance' => fake()->optional(0.5)->randomFloat(4, 1000, 500000),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

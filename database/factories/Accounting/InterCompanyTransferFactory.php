<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\InterCompanyTransfer;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterCompanyTransferFactory extends Factory
{
    protected $model = InterCompanyTransfer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'transfer_number' => 'ICT-' . fake()->unique()->numerify('######'),
            'transfer_type' => fake()->randomElement(['inter_branch', 'inter_company']),
            'from_branch_id' => null,
            'from_bank_account_id' => null,
            'to_branch_id' => null,
            'to_bank_account_id' => null,
            'to_organization_id' => null,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'transfer_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reference' => fake()->optional(0.5)->bothify('REF-####'),
            'purpose' => fake()->optional(0.5)->sentence(),
            'status' => fake()->randomElement(['draft', 'pending', 'approved', 'completed', 'cancelled']),
            'journal_entry_id' => null,
            'loan_id' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'fiscal_year_id' => null,
            'entry_number' => 'JE-' . fake()->unique()->numerify('######'),
            'entry_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'reference' => fake()->optional(0.5)->bothify('REF-####'),
            'description' => fake()->sentence(),
            'source_type' => null,
            'source_id' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'total_debit' => fake()->randomFloat(4, 100, 50000),
            'total_credit' => fake()->randomFloat(4, 100, 50000),
            'status' => fake()->randomElement(['draft', 'posted', 'voided']),
            'posted_at' => null,
            'posted_by' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'reversed_by_id' => null,
            'reversal_of_id' => null,
            'created_by' => null,
        ];
    }
}
<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'description' => fake()->optional(0.5)->sentence(),
            'debit' => fake()->randomFloat(4, 0, 50000),
            'credit' => 0,
            'base_debit' => fake()->randomFloat(4, 0, 50000),
            'base_credit' => 0,
            'cost_center_id' => null,
            'contact_id' => null,
            'line_order' => fake()->numberBetween(1, 10),
        ];
    }
}
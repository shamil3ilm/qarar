<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\RecurringJournalTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringJournalTemplateFactory extends Factory
{
    protected $model = RecurringJournalTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id'  => Organization::factory(),
            'name'             => fake()->words(3, true) . ' Journal',
            'description'      => fake()->optional(0.5)->sentence(),
            'frequency'        => fake()->randomElement([
                RecurringJournalTemplate::FREQUENCY_DAILY,
                RecurringJournalTemplate::FREQUENCY_WEEKLY,
                RecurringJournalTemplate::FREQUENCY_MONTHLY,
                RecurringJournalTemplate::FREQUENCY_QUARTERLY,
                RecurringJournalTemplate::FREQUENCY_ANNUALLY,
            ]),
            'interval'         => 1,
            'start_date'       => now()->toDateString(),
            'end_date'         => null,
            'next_run_date'    => now()->toDateString(),
            'last_run_date'    => null,
            'run_count'        => 0,
            'max_runs'         => null,
            'debit_account_id' => Account::factory(),
            'credit_account_id'=> Account::factory(),
            'amount'           => fake()->randomFloat(2, 100, 10000),
            'currency_code'    => 'SAR',
            'narration'        => fake()->optional(0.4)->sentence(),
            'is_active'        => true,
        ];
    }
}

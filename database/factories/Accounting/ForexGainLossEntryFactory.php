<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\ForexGainLossEntry;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForexGainLossEntryFactory extends Factory
{
    protected $model = ForexGainLossEntry::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'entry_type' => fake()->randomElement(['realized', 'unrealized']),
            'transaction_type' => fake()->randomElement(['invoice', 'bill', 'payment']),
            'source_type' => null,
            'source_id' => null,
            'foreign_currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'base_currency' => 'SAR',
            'foreign_amount' => fake()->randomFloat(4, 100, 50000),
            'original_rate' => fake()->randomFloat(8, 3.0, 4.0),
            'settlement_rate' => fake()->randomFloat(8, 3.0, 4.0),
            'gain_loss_amount' => fake()->randomFloat(4, -5000, 5000),
            'account_id' => null,
            'journal_entry_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
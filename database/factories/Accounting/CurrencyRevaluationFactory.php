<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyRevaluationFactory extends Factory
{
    protected $model = CurrencyRevaluation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'revaluation_number' => 'REVAL-' . fake()->unique()->numerify('######'),
            'revaluation_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency_code' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'old_rate' => fake()->randomFloat(8, 0.5, 5.0),
            'new_rate' => fake()->randomFloat(8, 0.5, 5.0),
            'base_currency' => 'SAR',
            'total_unrealized_gain' => fake()->randomFloat(4, 0, 10000),
            'total_unrealized_loss' => fake()->randomFloat(4, 0, 10000),
            'net_gain_loss' => fake()->randomFloat(4, -10000, 10000),
            'gain_loss_account_id' => null,
            'journal_entry_id' => null,
            'status' => fake()->randomElement(['draft', 'posted']),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}
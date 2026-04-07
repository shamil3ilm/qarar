<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\CurrencyRevaluationItem;
use App\Models\Accounting\CurrencyRevaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyRevaluationItemFactory extends Factory
{
    protected $model = CurrencyRevaluationItem::class;

    public function definition(): array
    {
        return [
            'revaluation_id' => CurrencyRevaluation::factory(),
            'account_id' => null,
            'account_type' => fake()->randomElement(['receivable', 'payable', 'bank']),
            'foreign_currency_balance' => fake()->randomFloat(4, 100, 100000),
            'old_base_amount' => fake()->randomFloat(4, 100, 100000),
            'new_base_amount' => fake()->randomFloat(4, 100, 100000),
            'gain_loss_amount' => fake()->randomFloat(4, -5000, 5000),
            'contact_id' => null,
        ];
    }
}
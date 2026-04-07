<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\BankReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationItemFactory extends Factory
{
    protected $model = BankReconciliationItem::class;

    public function definition(): array
    {
        return [
            'reconciliation_id' => BankReconciliation::factory(),
            'bank_transaction_id' => null,
            'item_type' => fake()->randomElement(['book', 'statement']),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reference' => fake()->bothify('REF-####'),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 50000),
            'is_cleared' => fake()->boolean(30),
        ];
    }
}
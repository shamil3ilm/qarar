<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\BackdatedTransaction;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackdatedTransactionFactory extends Factory
{
    protected $model = BackdatedTransaction::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'transaction_type' => fake()->randomElement(['invoice', 'payment', 'journal_entry']),
            'transaction_id' => fake()->numberBetween(1, 1000),
            'transaction_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'entry_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'reason' => fake()->sentence(),
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => null,
        ];
    }
}

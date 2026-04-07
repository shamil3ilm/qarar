<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\RecurringExpense;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringExpenseFactory extends Factory
{
    protected $model = RecurringExpense::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category_id' => null,
            'supplier_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'amount' => fake()->randomFloat(2, 50, 10000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'frequency' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'frequency_interval' => 1,
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'end_date' => fake()->optional(0.3)->dateTimeBetween('+6 months', '+2 years'),
            'next_occurrence' => fake()->dateTimeBetween('now', '+3 months'),
            'occurrences_count' => fake()->numberBetween(0, 24),
            'max_occurrences' => null,
            'auto_approve' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseBudget;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseBudgetFactory extends Factory
{
    protected $model = ExpenseBudget::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category_id' => null,
            'department_id' => null,
            'year' => now()->year,
            'month' => fake()->numberBetween(1, 12),
            'budget_amount' => fake()->randomFloat(2, 5000, 100000),
            'spent_amount' => fake()->randomFloat(2, 0, 50000),
            'committed_amount' => fake()->randomFloat(2, 0, 10000),
            'alert_at_80' => false,
            'alert_at_100' => false,
        ];
    }
}

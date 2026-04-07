<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseItem;
use App\Models\Expense\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'category_id' => null,
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'tax_amount' => fake()->randomFloat(2, 0, 500),
            'total_amount' => fake()->randomFloat(2, 10, 5500),
            'account_id' => null,
            'line_order' => fake()->numberBetween(1, 10),
        ];
    }
}

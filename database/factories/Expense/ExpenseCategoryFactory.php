<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseCategory;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement(['Travel', 'Office Supplies', 'Meals', 'Transport', 'Utilities']),
            'code' => strtoupper(fake()->unique()->lexify('EXC-???')),
            'icon' => null,
            'color' => fake()->optional(0.3)->hexColor(),
            'description' => fake()->optional(0.3)->sentence(),
            'default_account_id' => null,
            'is_active' => true,
            'requires_receipt' => fake()->boolean(50),
            'budget_limit' => fake()->optional(0.3)->randomFloat(2, 1000, 50000),
        ];
    }
}

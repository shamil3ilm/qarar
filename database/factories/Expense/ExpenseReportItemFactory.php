<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseReportItem;
use App\Models\Expense\ExpenseReport;
use App\Models\Expense\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseReportItemFactory extends Factory
{
    protected $model = ExpenseReportItem::class;

    public function definition(): array
    {
        return [
            'report_id' => ExpenseReport::factory(),
            'expense_id' => Expense::factory(),
            'approved_amount' => fake()->randomFloat(2, 10, 5000),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

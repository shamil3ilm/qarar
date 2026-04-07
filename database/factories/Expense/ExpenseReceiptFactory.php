<?php

declare(strict_types=1);

namespace Database\Factories\Expense;

use App\Models\Expense\ExpenseReceipt;
use App\Models\Expense\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseReceiptFactory extends Factory
{
    protected $model = ExpenseReceipt::class;

    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'file_name' => fake()->slug() . '.jpg',
            'file_path' => 'receipts/' . fake()->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'ocr_text' => fake()->optional(0.3)->paragraph(),
            'ocr_data' => null,
            'uploaded_by' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\PayslipItem;
use App\Models\HR\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayslipItemFactory extends Factory
{
    protected $model = PayslipItem::class;

    public function definition(): array
    {
        return [
            'payslip_id' => Payslip::factory(),
            'salary_component_id' => null,
            'type' => fake()->randomElement(['earning', 'deduction']),
            'name' => fake()->randomElement(['Basic Salary', 'HRA', 'Transport', 'Tax', 'Insurance']),
            'amount' => fake()->randomFloat(4, 100, 20000),
            'ytd_amount' => fake()->randomFloat(4, 1000, 200000),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}

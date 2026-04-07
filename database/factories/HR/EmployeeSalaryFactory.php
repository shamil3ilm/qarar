<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    public function definition(): array
    {
        $grossSalary = fake()->randomFloat(4, 3000, 25000);
        $netSalary = round($grossSalary * fake()->randomFloat(2, 0.75, 0.90), 4);
        $ctc = round($grossSalary * fake()->randomFloat(2, 1.10, 1.35), 4);

        return [
            'employee_id' => Employee::factory(),
            'salary_structure_id' => SalaryStructure::factory(),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => null,
            'ctc' => $ctc,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'reason_for_change' => fake()->optional(0.5)->randomElement([
                'Annual increment',
                'Promotion',
                'Market adjustment',
                'Performance bonus restructure',
            ]),
            'is_current' => true,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'is_current' => true,
            'effective_to' => null,
        ]);
    }

    public function historical(): static
    {
        $effectiveFrom = fake()->dateTimeBetween('-3 years', '-1 year');

        return $this->state(fn () => [
            'is_current' => false,
            'effective_from' => $effectiveFrom,
            'effective_to' => fake()->dateTimeBetween($effectiveFrom, '-6 months'),
        ]);
    }
}

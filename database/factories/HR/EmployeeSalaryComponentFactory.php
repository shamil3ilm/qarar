<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\EmployeeSalaryComponent;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeSalaryComponentFactory extends Factory
{
    protected $model = EmployeeSalaryComponent::class;

    public function definition(): array
    {
        return [
            'employee_salary_id' => EmployeeSalary::factory(),
            'salary_component_id' => SalaryComponent::factory(),
            'amount' => fake()->randomFloat(4, 100, 20000),
        ];
    }
}

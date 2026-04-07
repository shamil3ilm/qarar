<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\EmployeeQualification;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeQualificationFactory extends Factory
{
    protected $model = EmployeeQualification::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'qualification_type' => fake()->randomElement(['degree', 'diploma', 'certificate', 'training']),
            'qualification_name' => fake()->randomElement(['MBA', 'B.Tech', 'BBA', 'CPA', 'PMP']),
            'institution' => fake()->company() . ' University',
            'specialization' => fake()->optional(0.5)->randomElement(['Finance', 'Marketing', 'IT', 'HR']),
            'year_of_passing' => fake()->numberBetween(2000, 2024),
            'grade' => fake()->optional(0.5)->randomElement(['A', 'B+', 'B', 'First Class', 'Distinction']),
            'file_path' => fake()->optional(0.3)->filePath(),
        ];
    }
}

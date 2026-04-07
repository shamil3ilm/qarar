<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\EmployeeExperience;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeExperienceFactory extends Factory
{
    protected $model = EmployeeExperience::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_name' => fake()->company(),
            'designation' => fake()->jobTitle(),
            'from_date' => fake()->dateTimeBetween('-10 years', '-3 years'),
            'to_date' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'responsibilities' => fake()->optional(0.5)->paragraph(),
            'reason_for_leaving' => fake()->optional(0.5)->randomElement(['career growth', 'relocation', 'better opportunity']),
        ];
    }
}

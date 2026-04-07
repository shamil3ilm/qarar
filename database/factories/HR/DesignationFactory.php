<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        $designations = [
            'Software Engineer', 'Senior Software Engineer', 'Lead Engineer',
            'Project Manager', 'Product Manager', 'Business Analyst',
            'HR Manager', 'Accountant', 'Marketing Executive',
            'Sales Representative', 'DevOps Engineer', 'QA Engineer',
        ];

        $level = fake()->numberBetween(1, 10);

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->randomElement($designations),
            'code' => strtoupper(fake()->lexify('DES-???')),
            'description' => fake()->sentence(),
            'level' => $level,
            'min_salary' => fake()->randomFloat(4, 2000, 5000),
            'max_salary' => fake()->randomFloat(4, 8000, 25000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function atLevel(int $level): static
    {
        return $this->state(fn () => ['level' => $level]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $departments = [
            'Human Resources', 'Finance', 'Engineering', 'Marketing',
            'Sales', 'Operations', 'Legal', 'IT', 'Procurement',
            'Quality Assurance', 'Customer Support', 'Research & Development',
        ];

        $name = fake()->unique()->randomElement($departments);

        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => $name,
            'code' => strtoupper(fake()->lexify('DEPT-???')),
            'description' => fake()->sentence(),
            'manager_id' => null,
            'cost_center_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withParent(Department $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'organization_id' => $parent->organization_id,
        ]);
    }
}

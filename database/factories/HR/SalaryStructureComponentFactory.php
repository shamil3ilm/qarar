<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\SalaryStructureComponent;
use App\Models\HR\SalaryStructure;
use App\Models\HR\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryStructureComponentFactory extends Factory
{
    protected $model = SalaryStructureComponent::class;

    public function definition(): array
    {
        return [
            'salary_structure_id' => SalaryStructure::factory(),
            'salary_component_id' => SalaryComponent::factory(),
            'calculation_type' => fake()->randomElement(['fixed', 'percentage']),
            'value' => fake()->randomFloat(4, 100, 20000),
            'percentage_of' => null,
            'formula' => null,
        ];
    }
}

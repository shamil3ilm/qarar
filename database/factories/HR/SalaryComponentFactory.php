<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\SalaryComponent;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryComponentFactory extends Factory
{
    protected $model = SalaryComponent::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Basic Salary', 'HRA', 'Transport Allowance', 'Medical', 'Tax Deduction']),
            'code' => strtoupper(fake()->unique()->lexify('SC-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'type' => fake()->randomElement(['earning', 'deduction']),
            'category' => fake()->randomElement(['fixed', 'variable', 'statutory']),
            'calculation_type' => fake()->randomElement(['fixed', 'percentage', 'formula']),
            'default_value' => fake()->randomFloat(4, 100, 10000),
            'percentage_of' => null,
            'formula' => null,
            'is_taxable' => fake()->boolean(70),
            'is_pro_rata' => true,
            'is_statutory' => false,
            'is_flexible_benefit' => false,
            'show_in_payslip' => true,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}

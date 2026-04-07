<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Standard', 'Executive', 'Contractual', 'Intern']) . ' Structure',
            'code' => strtoupper(fake()->unique()->lexify('SS-???')),
            'description' => fake()->optional(0.5)->sentence(),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'payroll_frequency' => fake()->randomElement(['monthly', 'bi_weekly', 'weekly']),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

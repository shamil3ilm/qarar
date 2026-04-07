<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTierFactory extends Factory
{
    protected $model = LeaveTier::class;

    public function definition(): array
    {
        return [
            'leave_type_id' => null,
            'name' => fake()->words(3, true) . ' Tier',
            'description' => fake()->optional(0.3)->sentence(),
            'min_service_months' => fake()->numberBetween(0, 12),
            'max_service_months' => fake()->optional(0.5)->numberBetween(12, 120),
            'employee_grade' => null,
            'department_id' => null,
            'entitled_days' => fake()->randomFloat(2, 10, 30),
            'entitlement_period' => fake()->randomElement(['annual', 'monthly']),
            'monthly_accrual_rate' => fake()->randomFloat(2, 0.5, 3),
            'max_carryforward_days' => fake()->randomFloat(2, 0, 15),
            'carryforward_expiry_months' => fake()->optional(0.5)->numberBetween(1, 6),
            'max_encashable_days' => fake()->randomFloat(2, 0, 15),
            'encashment_rate' => fake()->optional(0.3)->randomFloat(2, 50, 200),
            'priority' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveBalance;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveAccrualFactory extends Factory
{
    protected $model = LeaveAccrual::class;

    public function definition(): array
    {
        return [
            'leave_balance_id' => LeaveBalance::factory(),
            'employee_id' => Employee::factory(),
            'accrual_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'accrual_type' => fake()->randomElement(['monthly', 'annual', 'manual']),
            'days' => fake()->randomFloat(2, 0.5, 5),
            'description' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}

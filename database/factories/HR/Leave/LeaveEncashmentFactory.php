<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveEncashment;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveEncashmentFactory extends Factory
{
    protected $model = LeaveEncashment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => null,
            'leave_balance_id' => null,
            'requested_days' => fake()->randomFloat(2, 1, 15),
            'approved_days' => fake()->randomFloat(2, 0, 15),
            'daily_rate' => fake()->randomFloat(2, 100, 2000),
            'encashment_rate' => fake()->randomFloat(2, 100, 2000),
            'amount' => fake()->randomFloat(2, 100, 30000),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'paid']),
            'approved_by' => null,
            'approved_at' => null,
            'payroll_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}

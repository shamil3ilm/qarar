<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveBalance;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => null,
            'leave_tier_id' => null,
            'year' => now()->year,
            'opening_balance' => 0,
            'entitled_days' => fake()->randomFloat(2, 10, 30),
            'accrued_days' => fake()->randomFloat(2, 0, 30),
            'adjustment_days' => 0,
            'used_days' => fake()->randomFloat(2, 0, 15),
            'pending_days' => fake()->randomFloat(2, 0, 5),
            'carried_forward' => 0,
            'encashed_days' => 0,
            'lapsed_days' => 0,
            'available_balance' => fake()->randomFloat(2, 0, 30),
            'last_accrual_date' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveAdjustmentFactory extends Factory
{
    protected $model = LeaveAdjustment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => null,
            'leave_balance_id' => null,
            'adjustment_type' => fake()->randomElement(['add', 'subtract']),
            'days' => fake()->randomFloat(2, 0.5, 10),
            'balance_before' => fake()->randomFloat(2, 0, 30),
            'balance_after' => fake()->randomFloat(2, 0, 30),
            'reason' => fake()->sentence(),
            'effective_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => null,
        ];
    }
}

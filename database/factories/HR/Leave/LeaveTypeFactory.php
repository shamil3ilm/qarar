<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveType;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'leave_policy_id' => null,
            'name' => fake()->randomElement(['Annual Leave', 'Sick Leave', 'Maternity', 'Paternity', 'Bereavement']),
            'code' => strtoupper(fake()->unique()->lexify('LT-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'color' => fake()->hexColor(),
            'icon' => null,
            'is_paid' => true,
            'is_encashable' => false,
            'is_carryforward_allowed' => fake()->boolean(50),
            'max_carryforward_days' => fake()->optional(0.3)->randomFloat(2, 5, 15),
            'requires_attachment' => false,
            'requires_reason' => true,
            'gender_restriction' => null,
            'employment_type_restriction' => null,
            'min_service_months' => 0,
            'max_consecutive_days' => fake()->optional(0.3)->numberBetween(5, 30),
            'min_days_per_request' => fake()->optional(0.3)->randomFloat(2, 0.5, 1),
            'max_days_per_request' => fake()->optional(0.3)->randomFloat(2, 5, 30),
            'allowed_days_of_week' => null,
            'blackout_dates' => null,
            'accrual_type' => fake()->randomElement(['annual', 'monthly', 'none']),
            'accrual_day' => null,
            'count_holidays' => false,
            'count_weekends' => false,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        $leaveTypes = [
            ['name' => 'Annual Leave', 'code' => 'AL'],
            ['name' => 'Sick Leave', 'code' => 'SL'],
            ['name' => 'Casual Leave', 'code' => 'CL'],
            ['name' => 'Maternity Leave', 'code' => 'ML'],
            ['name' => 'Paternity Leave', 'code' => 'PL'],
            ['name' => 'Unpaid Leave', 'code' => 'UL'],
            ['name' => 'Bereavement Leave', 'code' => 'BL'],
            ['name' => 'Study Leave', 'code' => 'STL'],
        ];

        $type = fake()->randomElement($leaveTypes);

        return [
            'organization_id' => Organization::factory(),
            'name' => $type['name'],
            'code' => $type['code'] . '-' . fake()->unique()->numerify('###'),
            'description' => fake()->optional(0.5)->sentence(),
            'annual_quota' => fake()->randomFloat(2, 5, 30),
            'is_paid' => fake()->boolean(70),
            'is_encashable' => fake()->boolean(30),
            'max_encashable_days' => fake()->randomFloat(2, 0, 15),
            'carry_forward' => fake()->boolean(40),
            'max_carry_forward_days' => fake()->randomFloat(2, 0, 15),
            'min_days_notice' => fake()->numberBetween(0, 7),
            'max_consecutive_days' => fake()->optional(0.5)->randomFloat(2, 5, 30),
            'requires_attachment' => fake()->boolean(20),
            'attachment_required_after_days' => fake()->numberBetween(0, 3),
            'half_day_allowed' => fake()->boolean(80),
            'requires_approval' => true,
            'applicable_gender' => 'all',
            'applicable_marital_status' => 'all',
            'applicable_after_months' => fake()->boolean(30) ? fake()->numberBetween(1, 12) : 0,
            'accrual_type' => fake()->randomElement(['annual', 'monthly', 'quarterly']),
            'prorate_on_joining' => true,
            'prorate_on_exit' => true,
            'color' => fake()->optional(0.5)->hexColor(),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['is_paid' => true]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['is_paid' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function encashable(): static
    {
        return $this->state(fn () => [
            'is_encashable' => true,
            'is_paid' => true,
        ]);
    }
}

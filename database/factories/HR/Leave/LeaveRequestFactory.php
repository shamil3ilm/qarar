<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveRequest;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => null,
            'leave_balance_id' => null,
            'request_number' => 'LR-' . fake()->unique()->numerify('######'),
            'start_date' => fake()->dateTimeBetween('now', '+1 month'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+2 months'),
            'total_days' => fake()->randomFloat(2, 0.5, 15),
            'day_type' => fake()->randomElement(['full', 'first_half', 'second_half']),
            'reason' => fake()->sentence(),
            'contact_during_leave' => fake()->optional(0.3)->phoneNumber(),
            'delegated_to' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
            'is_emergency' => false,
            'has_attachments' => false,
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'created_by' => null,
        ];
    }
}

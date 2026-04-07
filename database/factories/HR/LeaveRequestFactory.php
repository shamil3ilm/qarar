<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveRequest;
use App\Models\HR\Leave\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 day', '+30 days');
        $totalDays = fake()->numberBetween(1, 5);
        $endDate = (clone $startDate)->modify('+' . ($totalDays - 1) . ' days');

        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'leave_balance_id' => null,
            'request_number' => strtoupper(fake()->unique()->lexify('LR-####-???')),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'day_type' => LeaveRequest::DAY_TYPE_FULL,
            'reason' => fake()->sentence(),
            'contact_during_leave' => fake()->optional(0.5)->phoneNumber(),
            'delegated_to' => null,
            'status' => LeaveRequest::STATUS_PENDING,
            'is_emergency' => fake()->boolean(10),
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

    public function pending(): static
    {
        return $this->state(fn () => ['status' => LeaveRequest::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequest::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function halfDay(): static
    {
        return $this->state(fn () => [
            'day_type' => fake()->randomElement([
                LeaveRequest::DAY_TYPE_FIRST_HALF,
                LeaveRequest::DAY_TYPE_SECOND_HALF,
            ]),
            'total_days' => 0.5,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => ['is_emergency' => true]);
    }
}

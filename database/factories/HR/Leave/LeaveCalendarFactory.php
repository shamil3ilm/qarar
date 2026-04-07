<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveCalendar;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveCalendarFactory extends Factory
{
    protected $model = LeaveCalendar::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'leave_request_id' => null,
            'leave_type_id' => null,
            'leave_date' => fake()->dateTimeBetween('-3 months', '+3 months'),
            'day_type' => fake()->randomElement(['full', 'first_half', 'second_half']),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
        ];
    }
}

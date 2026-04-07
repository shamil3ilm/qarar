<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = fake()->unique()->dateTimeBetween('-365 days', 'now');
        $checkIn = (clone $date)->setTime(
            fake()->numberBetween(8, 10),
            fake()->numberBetween(0, 59)
        );
        $checkOut = (clone $checkIn)->modify('+' . fake()->numberBetween(7, 10) . ' hours');

        $workingHours = round(($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 3600, 2);

        return [
            'organization_id' => null,
            'employee_id' => Employee::factory(),
            'attendance_date' => $date->format('Y-m-d'),
            'work_schedule_id' => null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'break_start' => null,
            'break_end' => null,
            'working_hours' => $workingHours,
            'overtime_hours' => max(0, round($workingHours - 8, 2)),
            'break_hours' => 0,
            'late_minutes' => fake()->boolean(20) ? fake()->numberBetween(1, 30) : 0,
            'early_leaving_minutes' => fake()->boolean(10) ? fake()->numberBetween(1, 30) : 0,
            'status' => Attendance::STATUS_PRESENT,
            'source' => fake()->randomElement([
                Attendance::SOURCE_MANUAL,
                Attendance::SOURCE_BIOMETRIC,
            ]),
            'device_id' => null,
            'check_in_latitude' => null,
            'check_in_longitude' => null,
            'check_out_latitude' => null,
            'check_out_longitude' => null,
            'is_regularized' => false,
            'regularization_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => null,
        ];
    }

    public function present(): static
    {
        return $this->state(fn () => ['status' => Attendance::STATUS_PRESENT]);
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'status' => Attendance::STATUS_ABSENT,
            'check_in' => null,
            'check_out' => null,
            'working_hours' => 0,
            'overtime_hours' => 0,
        ]);
    }

    public function halfDay(): static
    {
        return $this->state(fn () => [
            'status' => Attendance::STATUS_HALF_DAY,
            'working_hours' => fake()->randomFloat(2, 3, 5),
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn () => [
            'status' => Attendance::STATUS_ON_LEAVE,
            'check_in' => null,
            'check_out' => null,
            'working_hours' => 0,
            'overtime_hours' => 0,
        ]);
    }

    public function workFromHome(): static
    {
        return $this->state(fn () => ['status' => Attendance::STATUS_WORK_FROM_HOME]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn () => ['attendance_date' => $date]);
    }
}

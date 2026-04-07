<?php

declare(strict_types=1);

namespace Database\Factories\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $startDatetime = fake()->dateTimeBetween('-7 days', '+14 days');
        $durationMinutes = fake()->randomElement([15, 30, 45, 60, 90, 120]);
        $endDatetime = (clone $startDatetime)->modify("+{$durationMinutes} minutes");

        return [
            'organization_id' => Organization::factory(),
            'activity_type' => fake()->randomElement([
                Activity::TYPE_CALL,
                Activity::TYPE_EMAIL,
                Activity::TYPE_MEETING,
                Activity::TYPE_TASK,
                Activity::TYPE_FOLLOW_UP,
            ]),
            'subject' => fake()->sentence(4),
            'description' => fake()->optional(0.6)->paragraph(),
            'related_type' => null,
            'related_id' => null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'duration_minutes' => $durationMinutes,
            'is_all_day' => false,
            'status' => Activity::STATUS_PLANNED,
            'priority' => fake()->randomElement([
                Activity::PRIORITY_LOW,
                Activity::PRIORITY_MEDIUM,
                Activity::PRIORITY_HIGH,
            ]),
            'completed_at' => null,
            'call_direction' => null,
            'call_result' => null,
            'location' => fake()->optional(0.3)->address(),
            'meeting_link' => fake()->optional(0.2)->url(),
            'assigned_to' => null,
            'attendees' => null,
            'reminder_datetime' => null,
            'reminder_sent' => false,
            'outcome' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => ['status' => Activity::STATUS_PLANNED]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => Activity::STATUS_IN_PROGRESS]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Activity::STATUS_COMPLETED,
            'completed_at' => now(),
            'outcome' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Activity::STATUS_CANCELLED]);
    }

    public function call(): static
    {
        return $this->state(fn () => [
            'activity_type' => Activity::TYPE_CALL,
            'call_direction' => fake()->randomElement([
                Activity::CALL_INBOUND,
                Activity::CALL_OUTBOUND,
            ]),
            'duration_minutes' => fake()->numberBetween(5, 45),
        ]);
    }

    public function meeting(): static
    {
        return $this->state(fn () => [
            'activity_type' => Activity::TYPE_MEETING,
            'location' => fake()->address(),
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
        ]);
    }

    public function task(): static
    {
        return $this->state(fn () => [
            'activity_type' => Activity::TYPE_TASK,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => Activity::PRIORITY_HIGH]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Activity::STATUS_PLANNED,
            'start_datetime' => fake()->dateTimeBetween('-14 days', '-1 day'),
            'end_datetime' => fake()->dateTimeBetween('-7 days', '-1 hour'),
        ]);
    }
}

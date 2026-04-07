<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarEvent;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 day', '+30 days');
        $endAt = (clone $startAt)->modify('+1 hour');

        return [
            'organization_id' => Organization::factory(),
            'calendar_id' => Calendar::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional(0.7)->paragraph(),
            'location' => fake()->optional(0.5)->address(),
            'event_type' => fake()->randomElement([
                CalendarEvent::TYPE_EVENT,
                CalendarEvent::TYPE_MEETING,
                CalendarEvent::TYPE_TASK,
            ]),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_all_day' => false,
            'timezone' => 'Asia/Riyadh',
            'status' => CalendarEvent::STATUS_CONFIRMED,
            'visibility' => CalendarEvent::VISIBILITY_DEFAULT,
            'related_type' => User::class,
            'related_id' => User::factory(),
            'is_recurring' => false,
        ];
    }

    public function allDay(): static
    {
        return $this->state(fn () => [
            'is_all_day' => true,
            'end_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CalendarEvent::STATUS_CANCELLED,
        ]);
    }

    public function meeting(): static
    {
        return $this->state(fn () => [
            'event_type' => CalendarEvent::TYPE_MEETING,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
        ]);
    }
}

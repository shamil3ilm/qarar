<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\CalendarEventReminder;
use App\Models\Calendar\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventReminderFactory extends Factory
{
    protected $model = CalendarEventReminder::class;

    public function definition(): array
    {
        return [
            'event_id' => CalendarEvent::factory(),
            'method' => fake()->randomElement(['email', 'push', 'sms']),
            'minutes_before' => fake()->randomElement([5, 10, 15, 30, 60, 1440]),
            'reminder_minutes' => fake()->randomElement([5, 10, 15, 30, 60]),
            'is_sent' => false,
            'sent_at' => null,
        ];
    }
}
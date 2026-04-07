<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventAttendeeFactory extends Factory
{
    protected $model = CalendarEventAttendee::class;

    public function definition(): array
    {
        return [
            'event_id' => CalendarEvent::factory(),
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['required', 'optional', 'organizer']),
            'status' => fake()->randomElement(['pending', 'accepted', 'declined', 'tentative']),
            'comment' => fake()->optional(0.3)->sentence(),
            'responded_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
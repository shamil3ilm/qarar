<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\CalendarRecurringRule;
use App\Models\Calendar\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarRecurringRuleFactory extends Factory
{
    protected $model = CalendarRecurringRule::class;

    public function definition(): array
    {
        return [
            'event_id' => CalendarEvent::factory(),
            'frequency' => fake()->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'interval' => fake()->numberBetween(1, 4),
            'by_day' => null,
            'days_of_week' => null,
            'by_month_day' => null,
            'by_month' => null,
            'until_date' => null,
            'ends_at' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+1 year'),
            'count' => fake()->optional(0.5)->numberBetween(5, 52),
            'exceptions' => null,
        ];
    }
}
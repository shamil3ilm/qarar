<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\Reminder;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.6)->sentence(),
            'remind_at' => fake()->dateTimeBetween('+1 hour', '+7 days'),
            'frequency' => Reminder::FREQUENCY_ONCE,
            'remindable_type' => User::class,
            'remindable_id' => User::factory(),
            'is_sent' => false,
            'sent_at' => null,
            'is_dismissed' => false,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'is_dismissed' => true,
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn () => [
            'frequency' => Reminder::FREQUENCY_DAILY,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn () => [
            'frequency' => Reminder::FREQUENCY_WEEKLY,
        ]);
    }
}

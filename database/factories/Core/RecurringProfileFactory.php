<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\RecurringProfile;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringProfileFactory extends Factory
{
    protected $model = RecurringProfile::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => fake()->words(3, true),
            'profile_type' => fake()->randomElement(['invoice', 'bill', 'journal']),
            'source_type' => null,
            'source_id' => null,
            'frequency' => fake()->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']),
            'interval' => 1,
            'schedule_config' => null,
            'start_date' => fake()->dateTimeBetween('now', '+1 month'),
            'end_date' => fake()->optional(0.5)->dateTimeBetween('+6 months', '+2 years'),
            'next_run_date' => fake()->dateTimeBetween('now', '+1 month'),
            'last_run_date' => null,
            'max_occurrences' => fake()->optional(0.3)->numberBetween(6, 60),
            'occurrences_count' => 0,
            'auto_send' => false,
            'send_reminder' => false,
            'reminder_days_before' => null,
            'status' => fake()->randomElement(['active', 'paused', 'completed', 'cancelled']),
            'notify_on_creation' => false,
        ];
    }
}

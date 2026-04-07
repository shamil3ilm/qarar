<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\RecurringProfileLog;
use App\Models\Core\RecurringProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringProfileLogFactory extends Factory
{
    protected $model = RecurringProfileLog::class;

    public function definition(): array
    {
        return [
            'recurring_profile_id' => RecurringProfile::factory(),
            'created_type' => null,
            'created_id' => null,
            'scheduled_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'created_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'status' => fake()->randomElement(['success', 'failed', 'skipped']),
            'error_message' => null,
        ];
    }
}

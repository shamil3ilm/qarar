<?php

declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Models\Automation\AutomationSchedule;
use App\Models\Automation\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationScheduleFactory extends Factory
{
    protected $model = AutomationSchedule::class;

    public function definition(): array
    {
        return [
            'rule_id' => AutomationRule::factory(),
            'scheduled_for' => fake()->dateTimeBetween('now', '+1 month'),
            'executed_at' => null,
            'status' => fake()->randomElement(['pending', 'executed', 'cancelled', 'failed']),
        ];
    }
}
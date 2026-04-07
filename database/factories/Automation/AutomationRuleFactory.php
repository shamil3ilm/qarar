<?php

declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Rule',
            'description' => fake()->optional(0.5)->sentence(),
            'trigger_type' => fake()->randomElement(['event', 'schedule', 'manual']),
            'trigger_event' => fake()->optional(0.5)->randomElement(['invoice.created', 'order.placed', 'payment.received']),
            'trigger_schedule' => null,
            'entity_type' => fake()->randomElement(['invoice', 'order', 'contact']),
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
            'actions' => [['type' => 'send_email', 'template_id' => 1]],
            'priority' => fake()->numberBetween(1, 100),
            'stop_on_match' => false,
            'is_active' => true,
            'execution_count' => fake()->numberBetween(0, 500),
            'last_executed_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'created_by' => null,
        ];
    }
}
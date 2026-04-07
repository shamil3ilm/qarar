<?php

declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Models\Automation\AutomationRuleLog;
use App\Models\Automation\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationRuleLogFactory extends Factory
{
    protected $model = AutomationRuleLog::class;

    public function definition(): array
    {
        return [
            'rule_id' => AutomationRule::factory(),
            'entity_type' => fake()->randomElement(['invoice', 'order', 'contact']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'status' => fake()->randomElement(['success', 'failed', 'skipped']),
            'conditions_matched' => [['field' => 'status', 'matched' => true]],
            'actions_executed' => [['type' => 'send_email', 'status' => 'sent']],
            'error_message' => null,
            'execution_time_ms' => fake()->numberBetween(10, 5000),
        ];
    }
}
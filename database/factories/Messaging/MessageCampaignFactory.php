<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\MessageCampaign;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageCampaignFactory extends Factory
{
    protected $model = MessageCampaign::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Campaign',
            'description' => fake()->optional(0.5)->sentence(),
            'trigger_event' => fake()->randomElement(['invoice.created', 'order.placed', 'payment.overdue']),
            'trigger_entity' => fake()->randomElement(['invoice', 'order', 'contact']),
            'timing' => fake()->randomElement(['immediate', 'delayed', 'scheduled']),
            'delay_minutes' => fake()->optional(0.3)->numberBetween(5, 1440),
            'delay_unit' => fake()->optional(0.3)->randomElement(['minutes', 'hours', 'days']),
            'conditions' => null,
            'channel_type' => fake()->randomElement(['email', 'sms', 'whatsapp']),
            'template_id' => null,
            'channel_id' => null,
            'recipient_type' => fake()->randomElement(['customer', 'all', 'segment']),
            'recipient_config' => null,
            'max_sends_per_contact' => 1,
            'rate_limit_period' => null,
            'is_active' => true,
            'execution_count' => 0,
            'last_executed_at' => null,
            'created_by' => null,
        ];
    }
}

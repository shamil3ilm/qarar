<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\MessageLog;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageLogFactory extends Factory
{
    protected $model = MessageLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'automation_id' => null,
            'template_id' => null,
            'channel_id' => null,
            'channel_type' => fake()->randomElement(['email', 'sms', 'whatsapp']),
            'sender' => fake()->safeEmail(),
            'recipient' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'contact_id' => null,
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'html_body' => null,
            'entity_type' => null,
            'entity_id' => null,
            'category' => fake()->randomElement(['transactional', 'marketing']),
            'status' => fake()->randomElement(['sent', 'delivered', 'failed', 'bounced']),
            'sent_at' => now(),
            'delivered_at' => fake()->optional(0.7)->dateTimeBetween('-1 hour', 'now'),
            'opened_at' => null,
            'clicked_at' => null,
        ];
    }
}

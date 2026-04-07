<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\MessageTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'code' => fake()->unique()->slug(2),
            'channel_type' => fake()->randomElement(['email', 'sms', 'whatsapp']),
            'category' => fake()->randomElement(['transactional', 'marketing', 'notification']),
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraph(),
            'html_body' => '<p>' . fake()->paragraph() . '</p>',
            'variables' => ['name', 'amount', 'date'],
            'attachments_config' => null,
            'language' => 'en',
            'parent_template_id' => null,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}

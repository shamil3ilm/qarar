<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\Conversation;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subject' => fake()->sentence(4),
            'type' => fake()->randomElement(['direct', 'group', 'channel']),
            'created_by' => null,
            'last_message_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
        ];
    }
}

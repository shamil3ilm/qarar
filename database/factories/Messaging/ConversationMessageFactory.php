<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\ConversationMessage;
use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationMessageFactory extends Factory
{
    protected $model = ConversationMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['text', 'file', 'image']),
            'file_url' => null,
            'file_name' => null,
            'file_size' => null,
        ];
    }
}

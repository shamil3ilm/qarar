<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\OutboundMessageAttachment;
use App\Models\Messaging\OutboundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutboundMessageAttachmentFactory extends Factory
{
    protected $model = OutboundMessageAttachment::class;

    public function definition(): array
    {
        return [
            'message_id' => OutboundMessage::factory(),
            'file_name' => fake()->slug() . '.pdf',
            'file_path' => 'attachments/' . fake()->uuid() . '.pdf',
            'file_type' => fake()->randomElement(['pdf', 'xlsx', 'jpg']),
            'file_size' => fake()->numberBetween(1024, 5242880),
            'is_inline' => false,
        ];
    }
}

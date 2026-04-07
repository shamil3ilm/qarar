<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\ChannelTemplateApproval;
use App\Models\Messaging\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelTemplateApprovalFactory extends Factory
{
    protected $model = ChannelTemplateApproval::class;

    public function definition(): array
    {
        return [
            'template_id' => MessageTemplate::factory(),
            'channel_type' => fake()->randomElement(['whatsapp', 'sms']),
            'provider_template_id' => fake()->optional(0.5)->uuid(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'rejection_reason' => null,
            'submitted_at' => now(),
            'approved_at' => null,
        ];
    }
}

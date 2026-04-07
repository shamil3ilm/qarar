<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\MessagingConfiguration;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessagingConfigurationFactory extends Factory
{
    protected $model = MessagingConfiguration::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel_type' => fake()->randomElement(['email', 'sms', 'whatsapp']),
            'name' => fake()->words(2, true) . ' Config',
            'provider' => fake()->randomElement(['smtp', 'twilio', 'unifonic', 'whatsapp_cloud']),
            'credentials' => null,
            'settings' => null,
            'sender_name' => fake()->company(),
            'sender_address' => fake()->safeEmail(),
            'is_default' => true,
            'is_active' => true,
        ];
    }
}

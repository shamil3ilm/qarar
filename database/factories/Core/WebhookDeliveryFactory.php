<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\WebhookDelivery;
use App\Models\Core\Webhook;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'webhook_id' => Webhook::factory(),
            'event_type' => fake()->randomElement(['invoice.created', 'payment.received']),
            'payload' => ['id' => fake()->numberBetween(1, 1000), 'event' => 'test'],
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
            'http_status' => fake()->randomElement([200, 201, 400, 500, null]),
            'response_body' => fake()->optional(0.3)->sentence(),
            'response_headers' => null,
            'duration_ms' => fake()->numberBetween(50, 5000),
            'attempt' => fake()->numberBetween(1, 3),
            'next_retry_at' => null,
            'error_message' => null,
        ];
    }
}

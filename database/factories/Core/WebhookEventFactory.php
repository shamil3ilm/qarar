<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\WebhookEvent;
use App\Models\Core\Organization;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'event_type' => fake()->randomElement(['invoice.created', 'payment.received', 'contact.updated']),
            'resource_type' => fake()->randomElement(['invoice', 'payment', 'contact']),
            'resource_id' => fake()->numberBetween(1, 1000),
            'data' => ['id' => fake()->numberBetween(1, 1000)],
            'webhooks_triggered' => fake()->numberBetween(0, 5),
        ];
    }
}

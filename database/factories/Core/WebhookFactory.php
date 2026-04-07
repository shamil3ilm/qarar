<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Webhook;
use App\Models\Core\Organization;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'created_by' => null,
            'name' => fake()->words(3, true) . ' Webhook',
            'url' => fake()->url() . '/webhook',
            'secret' => Str::random(32),
            'events' => ['invoice.created', 'payment.received'],
            'headers' => null,
            'is_active' => true,
            'retry_count' => 3,
            'timeout_seconds' => 30,
            'content_type' => 'application/json',
            'last_triggered_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'success_count' => 0,
            'failure_count' => 0,
        ];
    }
}

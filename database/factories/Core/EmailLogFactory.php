<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\EmailLog;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'template_code' => fake()->slug(2),
            'emailable_type' => null,
            'emailable_id' => null,
            'to_email' => fake()->safeEmail(),
            'to_name' => fake()->name(),
            'subject' => fake()->sentence(6),
            'body_preview' => fake()->sentence(),
            'attachments' => null,
            'status' => fake()->randomElement(['queued', 'sent', 'failed', 'bounced']),
            'error_message' => null,
            'sent_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'opened_at' => null,
            'clicked_at' => null,
            'bounced_at' => null,
            'message_id' => fake()->optional(0.5)->uuid(),
        ];
    }
}

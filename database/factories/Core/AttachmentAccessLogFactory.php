<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\AttachmentAccessLog;
use App\Models\Core\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentAccessLogFactory extends Factory
{
    protected $model = AttachmentAccessLog::class;

    public function definition(): array
    {
        return [
            'attachment_id' => Attachment::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['view', 'download', 'share']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

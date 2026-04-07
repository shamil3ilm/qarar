<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Attachment;
use App\Models\Core\Organization;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'attachable_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Purchase\Bill']),
            'attachable_id' => fake()->numberBetween(1, 1000),
            'file_name' => fake()->slug() . '.' . fake()->fileExtension(),
            'original_name' => fake()->words(3, true) . '.' . fake()->fileExtension(),
            'mime_type' => fake()->mimeType(),
            'file_size' => fake()->numberBetween(1024, 10485760),
            'disk' => 'local',
            'path' => 'attachments/' . fake()->slug() . '.' . fake()->fileExtension(),
            'category' => fake()->optional(0.5)->randomElement(['document', 'image', 'receipt']),
            'description' => fake()->optional(0.3)->sentence(),
            'metadata' => null,
            'is_public' => false,
            'visibility' => 'private',
            'expires_at' => null,
            'uploaded_by' => null,
        ];
    }
}

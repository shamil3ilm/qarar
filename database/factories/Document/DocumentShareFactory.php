<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DocumentShare;
use App\Models\Document\Document;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentShareFactory extends Factory
{
    protected $model = DocumentShare::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'shared_by' => null,
            'share_type' => fake()->randomElement(['link', 'email']),
            'recipient_email' => fake()->optional(0.5)->safeEmail(),
            'access_code' => Str::random(32),
            'allow_download' => true,
            'max_downloads' => fake()->optional(0.3)->numberBetween(1, 100),
            'download_count' => 0,
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 week', '+3 months'),
            'is_active' => true,
        ];
    }
}

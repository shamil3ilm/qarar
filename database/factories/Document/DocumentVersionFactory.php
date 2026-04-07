<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DocumentVersion;
use App\Models\Document\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => fake()->numberBetween(1, 20),
            'file_path' => 'documents/versions/' . fake()->uuid() . '.pdf',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'mime_type' => fake()->mimeType(),
            'change_summary' => fake()->optional(0.5)->sentence(),
            'change_notes' => fake()->optional(0.3)->paragraph(),
            'uploaded_by' => null,
        ];
    }
}

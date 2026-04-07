<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Core\Organization;
use App\Models\Document\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'docx', 'xlsx', 'png', 'jpg']);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
        ];
        $fileName = fake()->slug(3) . '.' . $extension;

        return [
            'organization_id' => Organization::factory(),
            'folder_id' => null,
            'name' => fake()->words(3, true),
            'file_name' => $fileName,
            'file_path' => 'documents/' . $fileName,
            'mime_type' => $mimeTypes[$extension],
            'file_size' => fake()->numberBetween(1024, 10485760),
            'extension' => $extension,
            'description' => fake()->optional(0.5)->sentence(),
            'tags' => null,
            'document_type' => fake()->randomElement(Document::DOCUMENT_TYPES),
            'document_date' => fake()->optional(0.5)->date(),
            'expiry_date' => null,
            'is_expiry_notified' => false,
            'documentable_type' => User::class,
            'documentable_id' => User::factory(),
            'access_level' => Document::ACCESS_ORGANIZATION,
            'is_archived' => false,
            'uploaded_by' => User::factory(),
            'download_count' => 0,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'is_archived' => true,
        ]);
    }

    public function expiringSoon(int $days = 15): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->addDays($days),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->subDays(5),
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_name' => fake()->slug(3) . '.pdf',
        ]);
    }
}

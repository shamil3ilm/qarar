<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DocumentPermission;
use App\Models\Document\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentPermissionFactory extends Factory
{
    protected $model = DocumentPermission::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'folder_id' => null,
            'permissible_type' => 'App\Models\User',
            'permissible_id' => fake()->numberBetween(1, 100),
            'permission' => fake()->randomElement(['view', 'edit', 'manage']),
            'granted_by' => null,
        ];
    }
}

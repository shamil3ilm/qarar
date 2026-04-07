<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DocumentFolder;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'color' => fake()->optional(0.3)->hexColor(),
            'icon' => null,
            'description' => fake()->optional(0.3)->sentence(),
            'is_system' => false,
            'access_level' => fake()->randomElement(['private', 'team', 'organization']),
            'created_by' => null,
        ];
    }
}

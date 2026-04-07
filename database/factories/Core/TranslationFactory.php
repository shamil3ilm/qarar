<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Translation;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'language_code' => fake()->randomElement(['en', 'ar', 'hi']),
            'group' => fake()->randomElement(['labels', 'messages', 'validation']),
            'key' => fake()->slug(3),
            'value' => fake()->sentence(),
        ];
    }
}

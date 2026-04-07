<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->languageCode(),
            'name' => fake()->word(),
            'native_name' => fake()->word(),
            'direction' => fake()->randomElement(['ltr', 'rtl']),
            'locale' => fake()->locale(),
            'flag_icon' => null,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}

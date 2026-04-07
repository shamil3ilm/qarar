<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\ExciseCategory;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExciseCategoryFactory extends Factory
{
    protected $model = ExciseCategory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('EXC-???')),
            'description' => fake()->optional(0.5)->sentence(),
            'country_code' => fake()->randomElement(['SA', 'AE', 'BH']),
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Tax\TaxCategory;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxCategoryFactory extends Factory
{
    protected $model = TaxCategory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Standard Rate', 'Reduced Rate', 'Zero Rate', 'Exempt']),
            'code' => strtoupper(fake()->unique()->lexify('TAX-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'uuid'            => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'product_id'      => Product::factory(),
            'recipe_code'     => strtoupper(fake()->unique()->bothify('RCP-####')),
            'name'            => fake()->words(3, true),
            'base_quantity'   => 1,
            'base_unit_id'    => null,
            'recipe_type'     => 'master',
            'validity_from'   => now()->format('Y-m-d'),
            'validity_to'     => null,
            'is_active'       => true,
        ];
    }
}

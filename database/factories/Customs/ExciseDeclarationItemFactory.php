<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\ExciseDeclarationItem;
use App\Models\Customs\ExciseDeclaration;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExciseDeclarationItemFactory extends Factory
{
    protected $model = ExciseDeclarationItem::class;

    public function definition(): array
    {
        return [
            'declaration_id' => ExciseDeclaration::factory(),
            'product_id' => null,
            'excise_category_id' => null,
            'excise_rate_id' => null,
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(4, 1, 10000),
            'unit' => fake()->randomElement(['KG', 'L', 'PCS']),
            'excisable_value' => fake()->randomFloat(4, 100, 100000),
            'excise_rate' => fake()->randomFloat(2, 5, 100),
            'excise_rate_applied' => fake()->randomFloat(4, 5, 100),
            'excise_amount' => fake()->randomFloat(4, 10, 50000),
            'notes' => null,
        ];
    }
}

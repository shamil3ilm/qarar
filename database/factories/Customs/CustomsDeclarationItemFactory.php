<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\CustomsDeclarationItem;
use App\Models\Customs\CustomsDeclaration;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomsDeclarationItemFactory extends Factory
{
    protected $model = CustomsDeclarationItem::class;

    public function definition(): array
    {
        return [
            'declaration_id' => CustomsDeclaration::factory(),
            'product_id' => null,
            'variant_id' => null,
            'item_number' => fake()->numberBetween(1, 20),
            'description' => fake()->sentence(),
            'tariff_code' => fake()->numerify('########'),
            'tariff_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 10000),
            'unit' => fake()->randomElement(['KG', 'PCS', 'L', 'M']),
            'gross_weight_kg' => fake()->randomFloat(4, 1, 50000),
            'net_weight_kg' => fake()->randomFloat(4, 1, 45000),
            'unit_value' => fake()->randomFloat(4, 1, 5000),
            'total_value' => fake()->randomFloat(4, 100, 500000),
            'assessable_value' => fake()->randomFloat(4, 100, 500000),
            'duty_rate' => fake()->randomFloat(2, 0, 25),
            'duty_amount' => fake()->randomFloat(4, 0, 50000),
            'vat_rate' => fake()->randomFloat(2, 0, 15),
            'vat_amount' => fake()->randomFloat(4, 0, 50000),
            'excise_rate' => 0,
            'excise_amount' => 0,
        ];
    }
}

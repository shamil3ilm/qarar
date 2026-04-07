<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ShelfLabel;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShelfLabelFactory extends Factory
{
    protected $model = ShelfLabel::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'product_id' => Product::factory(),
            'variant_id' => null,
            'product_name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-####-???')),
            'barcode_value' => fake()->ean13(),
            'price' => fake()->randomFloat(4, 1, 5000),
            'compare_at_price' => fake()->optional(0.3)->randomFloat(4, 5, 6000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'unit_label' => fake()->optional(0.3)->randomElement(['per kg', 'per piece', 'per liter']),
            'price_per_unit' => fake()->optional(0.3)->randomFloat(4, 1, 100),
            'unit_measure_label' => null,
            'aisle' => fake()->optional(0.5)->randomElement(['A', 'B', 'C', 'D']),
            'shelf' => fake()->optional(0.5)->numberBetween(1, 10),
            'position' => fake()->optional(0.5)->numberBetween(1, 20),
            'label_type' => fake()->randomElement(['standard', 'promotional', 'clearance']),
            'label_size' => fake()->randomElement(['small', 'medium', 'large']),
            'is_digital' => false,
            'esl_device_id' => null,
            'last_synced_at' => null,
            'needs_reprint' => false,
            'last_printed_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'print_count' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}

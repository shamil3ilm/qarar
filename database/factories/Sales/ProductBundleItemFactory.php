<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\ProductBundle;
use App\Models\Sales\ProductBundleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleItemFactory extends Factory
{
    protected $model = ProductBundleItem::class;

    public function definition(): array
    {
        $originalPrice = fake()->randomFloat(4, 10, 2000);
        $bundlePrice = round($originalPrice * fake()->randomFloat(2, 0.7, 0.95), 4);

        return [
            'bundle_id' => ProductBundle::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 10),
            'original_price' => $originalPrice,
            'bundle_price' => $bundlePrice,
            'is_optional' => false,
            'is_default_selected' => true,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function optional(): static
    {
        return $this->state(fn () => [
            'is_optional' => true,
            'is_default_selected' => false,
        ]);
    }

    public function optionalSelected(): static
    {
        return $this->state(fn () => [
            'is_optional' => true,
            'is_default_selected' => true,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn () => [
            'is_optional' => false,
            'is_default_selected' => true,
        ]);
    }
}

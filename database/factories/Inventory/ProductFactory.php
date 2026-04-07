<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $type = fake()->randomElement([Product::TYPE_GOODS, Product::TYPE_SERVICE]);
        $purchasePrice = fake()->randomFloat(4, 10, 5000);
        $sellingPrice = $purchasePrice * fake()->randomFloat(2, 1.1, 2.5);
        $minimumPrice = $purchasePrice * fake()->randomFloat(2, 1.0, 1.2);

        return [
            'organization_id' => Organization::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'barcode' => fake()->optional(0.7)->ean13(),
            'name' => fake()->words(fake()->numberBetween(2, 5), true),
            'description' => fake()->optional(0.8)->sentence(),
            'type' => $type,
            'category_id' => null,
            'unit_id' => UnitOfMeasure::factory(),
            'purchase_price' => round($purchasePrice, 4),
            'selling_price' => round($sellingPrice, 4),
            'minimum_price' => round($minimumPrice, 4),
            'tax_category_id' => null,
            'hsn_code' => fake()->optional(0.5)->numerify('########'),
            'income_account_id' => null,
            'expense_account_id' => null,
            'inventory_account_id' => null,
            'costing_method' => fake()->randomElement([
                Product::COSTING_FIFO,
                Product::COSTING_WEIGHTED_AVERAGE,
                Product::COSTING_STANDARD,
            ]),
            'track_inventory' => $type === Product::TYPE_GOODS,
            'reorder_level' => $type === Product::TYPE_GOODS ? fake()->randomFloat(4, 5, 50) : null,
            'reorder_quantity' => $type === Product::TYPE_GOODS ? fake()->randomFloat(4, 10, 100) : null,
            'weight' => fake()->optional(0.5)->randomFloat(3, 0.1, 100),
            'weight_unit' => fake()->optional(0.5)->randomElement(['kg', 'g', 'lb', 'oz']),
            'length' => null,
            'width' => null,
            'height' => null,
            'dimension_unit' => null,
            'image_url' => null,
            'gallery_urls' => null,
            'is_active' => true,
            'is_purchasable' => true,
            'is_sellable' => true,
        ];
    }

    public function goods(): static
    {
        return $this->state(fn () => [
            'type' => Product::TYPE_GOODS,
            'track_inventory' => true,
        ]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'type' => Product::TYPE_SERVICE,
            'track_inventory' => false,
            'reorder_level' => null,
            'reorder_quantity' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function withCosting(string $method): static
    {
        return $this->state(fn () => [
            'costing_method' => $method,
        ]);
    }
}

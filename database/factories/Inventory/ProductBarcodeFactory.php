<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductBarcode;
use App\Models\Inventory\Product;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'batch_id' => null,
            'barcode_value' => fake()->ean13(),
            'barcode_type' => fake()->randomElement(['ean13', 'ean8', 'upc', 'code128', 'qr']),
            'barcode_image_path' => null,
            'usage' => fake()->randomElement(['pos', 'inventory', 'shipping']),
            'is_primary' => true,
            'gtin' => fake()->optional(0.3)->ean13(),
            'gs1_company_prefix' => null,
            'is_active' => true,
        ];
    }
}

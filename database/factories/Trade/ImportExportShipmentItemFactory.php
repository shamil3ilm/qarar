<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\ImportExportShipmentItem;
use App\Models\Trade\ImportExportShipment;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportExportShipmentItemFactory extends Factory
{
    protected $model = ImportExportShipmentItem::class;

    public function definition(): array
    {
        return [
            'shipment_id' => ImportExportShipment::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(4, 1, 10000),
            'unit' => fake()->randomElement(['KG', 'PCS', 'L', 'M']),
            'unit_price' => fake()->randomFloat(4, 1, 5000),
            'total_value' => fake()->randomFloat(4, 100, 500000),
            'weight_kg' => fake()->randomFloat(4, 0.1, 50000),
            'tariff_code' => fake()->numerify('########'),
            'country_of_origin' => fake()->countryCode(),
        ];
    }
}

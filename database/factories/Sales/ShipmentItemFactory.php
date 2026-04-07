<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ShipmentItem;
use App\Models\Sales\Shipment;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'weight_kg' => fake()->optional(0.5)->randomFloat(2, 0.1, 50),
            'serial_numbers' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\PriceCheckLog;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceCheckLogFactory extends Factory
{
    protected $model = PriceCheckLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'station_id' => null,
            'branch_id' => null,
            'scan_type' => fake()->randomElement(['barcode', 'qr', 'manual']),
            'scan_value' => fake()->ean13(),
            'scan_successful' => true,
            'product_id' => null,
            'variant_id' => null,
            'product_name' => fake()->words(3, true),
            'product_sku' => strtoupper(fake()->bothify('SKU-####')),
            'displayed_price' => fake()->randomFloat(4, 1, 5000),
            'original_price' => fake()->randomFloat(4, 1, 5000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'has_promotion' => false,
            'promotion_name' => null,
            'promotion_discount' => null,
            'contact_id' => null,
            'loyalty_tier' => null,
            'stock_available' => fake()->randomFloat(4, 0, 1000),
            'stock_status' => fake()->randomElement(['in_stock', 'low_stock', 'out_of_stock']),
            'error_type' => null,
            'error_message' => null,
            'scanned_at' => now(),
        ];
    }
}

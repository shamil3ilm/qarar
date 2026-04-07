<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\PriceCheckStation;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceCheckStationFactory extends Factory
{
    protected $model = PriceCheckStation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => 'Station ' . fake()->numberBetween(1, 20),
            'station_code' => strtoupper(fake()->unique()->bothify('PCS-###')),
            'location_description' => fake()->optional(0.5)->sentence(),
            'device_type' => fake()->randomElement(['kiosk', 'handheld', 'fixed_scanner']),
            'device_id' => fake()->optional(0.3)->uuid(),
            'scanner_type' => fake()->randomElement(['laser', 'camera', 'rfid']),
            'scan_barcode' => true,
            'scan_qr' => true,
            'scan_rfid' => false,
            'scan_nfc' => false,
            'manual_entry' => true,
            'show_price' => true,
            'show_stock' => true,
            'show_promotions' => true,
            'show_alternatives' => false,
            'show_loyalty_points' => false,
            'show_product_image' => true,
            'show_description' => false,
            'show_location' => true,
            'price_list_id' => null,
            'use_customer_price' => false,
            'api_token' => null,
            'status' => fake()->randomElement(['active', 'inactive', 'maintenance']),
            'last_heartbeat_at' => fake()->optional(0.5)->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}

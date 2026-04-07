<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockTransfer;
use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'transfer_number' => 'ST-' . fake()->unique()->numerify('######'),
            'transfer_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'expected_arrival_date' => fake()->optional(0.7)->dateTimeBetween('now', '+2 weeks'),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => fake()->randomElement(['draft', 'in_transit', 'received', 'cancelled']),
            'shipped_at' => null,
            'shipped_by' => null,
            'received_at' => null,
            'received_by' => null,
            'created_by' => null,
        ];
    }
}

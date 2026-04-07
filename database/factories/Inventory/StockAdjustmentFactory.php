<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockAdjustment;
use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'warehouse_id' => Warehouse::factory(),
            'adjustment_number' => 'SA-' . fake()->unique()->numerify('######'),
            'adjustment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reason' => fake()->randomElement(['stock_count', 'damage', 'theft', 'correction', 'expired']),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => fake()->randomElement(['draft', 'posted', 'cancelled']),
            'posted_at' => null,
            'posted_by' => null,
            'created_by' => null,
        ];
    }
}

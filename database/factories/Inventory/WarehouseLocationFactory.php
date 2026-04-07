<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\WarehouseLocation;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement(['Zone A', 'Zone B', 'Shelf 1', 'Bin 1', 'Rack A-1']),
            'code' => strtoupper(fake()->unique()->bothify('LOC-??-##')),
            'type' => fake()->randomElement(['zone', 'aisle', 'rack', 'shelf', 'bin']),
            'is_active' => true,
        ];
    }
}

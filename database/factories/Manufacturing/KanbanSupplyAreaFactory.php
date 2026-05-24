<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\KanbanSupplyArea;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KanbanSupplyAreaFactory extends Factory
{
    protected $model = KanbanSupplyArea::class;

    public function definition(): array
    {
        return [
            'uuid'            => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'code'            => strtoupper(fake()->unique()->bothify('KSA-###')),
            'name'            => fake()->words(2, true) . ' Area',
            'warehouse_id'    => Warehouse::factory(),
            'location_id'     => null,
        ];
    }
}

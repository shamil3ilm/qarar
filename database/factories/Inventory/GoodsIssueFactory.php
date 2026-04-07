<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Core\Organization;
use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsIssue>
 */
class GoodsIssueFactory extends Factory
{
    protected $model = GoodsIssue::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'gi_number'       => strtoupper(fake()->unique()->lexify('GI-###-???')),
            'gi_date'         => now()->format('Y-m-d'),
            'movement_type'   => GoodsIssue::MOVEMENT_OTHER,
            'warehouse_id'    => Warehouse::factory(),
            'status'          => GoodsIssue::STATUS_DRAFT,
            'total_quantity'  => 0,
            'total_value'     => 0,
            'created_by'      => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => GoodsIssue::STATUS_DRAFT]);
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status'    => GoodsIssue::STATUS_POSTED,
            'posted_at' => now(),
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'status'      => GoodsIssue::STATUS_REVERSED,
            'reversed_at' => now(),
        ]);
    }

    public function salesDelivery(): static
    {
        return $this->state(fn () => ['movement_type' => GoodsIssue::MOVEMENT_SALES_DELIVERY]);
    }

    public function productionIssue(): static
    {
        return $this->state(fn () => ['movement_type' => GoodsIssue::MOVEMENT_PRODUCTION_ISSUE]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\InspectionLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionLot>
 */
class InspectionLotFactory extends Factory
{
    protected $model = InspectionLot::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 10, 500);

        return [
            'organization_id'   => Organization::factory(),
            'product_id'        => Product::factory(),
            'lot_number'        => strtoupper(fake()->unique()->lexify('LOT-###-???')),
            'source_type'       => InspectionLot::SOURCE_MANUAL,
            'quantity'          => $quantity,
            'inspected_quantity'=> 0,
            'accepted_quantity' => 0,
            'rejected_quantity' => 0,
            'status'            => InspectionLot::STATUS_PENDING,
            'created_by'        => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => InspectionLot::STATUS_PENDING]);
    }

    public function inInspection(): static
    {
        return $this->state(fn () => ['status' => InspectionLot::STATUS_IN_INSPECTION]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'             => InspectionLot::STATUS_ACCEPTED,
            'accepted_quantity'  => $attrs['quantity'],
            'inspected_quantity' => $attrs['quantity'],
            'rejected_quantity'  => 0,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'             => InspectionLot::STATUS_REJECTED,
            'rejected_quantity'  => $attrs['quantity'],
            'inspected_quantity' => $attrs['quantity'],
            'accepted_quantity'  => 0,
        ]);
    }

    public function fromProduction(): static
    {
        return $this->state(fn () => ['source_type' => InspectionLot::SOURCE_PRODUCTION]);
    }
}

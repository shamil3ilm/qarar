<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Core\Organization;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        $units = [
            ['name' => 'Kilogram', 'symbol' => 'kg', 'factor' => '1.00000000'],
            ['name' => 'Gram', 'symbol' => 'g', 'factor' => '0.00100000'],
            ['name' => 'Piece', 'symbol' => 'pcs', 'factor' => '1.00000000'],
            ['name' => 'Meter', 'symbol' => 'm', 'factor' => '1.00000000'],
            ['name' => 'Centimeter', 'symbol' => 'cm', 'factor' => '0.01000000'],
            ['name' => 'Liter', 'symbol' => 'L', 'factor' => '1.00000000'],
            ['name' => 'Milliliter', 'symbol' => 'mL', 'factor' => '0.00100000'],
            ['name' => 'Box', 'symbol' => 'box', 'factor' => '1.00000000'],
            ['name' => 'Dozen', 'symbol' => 'dz', 'factor' => '12.00000000'],
            ['name' => 'Pound', 'symbol' => 'lb', 'factor' => '0.45359237'],
        ];

        $unit = fake()->randomElement($units);

        return [
            'organization_id' => Organization::factory(),
            'name' => $unit['name'],
            'symbol' => $unit['symbol'],
            'base_unit_id' => null,
            'conversion_factor' => $unit['factor'],
            'is_active' => true,
        ];
    }

    public function baseUnit(): static
    {
        return $this->state(fn () => [
            'base_unit_id' => null,
            'conversion_factor' => '1.00000000',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function piece(): static
    {
        return $this->state(fn () => [
            'name' => 'Piece',
            'symbol' => 'pcs',
            'conversion_factor' => '1.00000000',
        ]);
    }

    public function kilogram(): static
    {
        return $this->state(fn () => [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'conversion_factor' => '1.00000000',
        ]);
    }
}

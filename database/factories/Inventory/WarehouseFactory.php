<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Core\Organization;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => fake()->city() . ' Warehouse',
            'code' => strtoupper(fake()->unique()->lexify('WH-???')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country_code' => fake()->randomElement(['SA', 'AE', 'QA', 'OM', 'BH', 'KW', 'IN']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'manager_id' => null,
            'is_default' => false,
            'is_active' => true,
            'allow_negative_stock' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function allowNegativeStock(): static
    {
        return $this->state(fn () => [
            'allow_negative_stock' => true,
        ]);
    }
}

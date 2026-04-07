<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomTemplateFactory extends Factory
{
    protected $model = BomTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bom_number' => strtoupper(fake()->unique()->lexify('BOM-####-???')),
            'name' => fake()->words(3, true) . ' Assembly',
            'description' => fake()->optional(0.5)->sentence(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'output_quantity' => fake()->randomFloat(4, 1, 100),
            'output_unit_id' => null,
            'default_warehouse_id' => null,
            'estimated_hours' => null,
            'estimated_labor_cost' => fake()->randomFloat(4, 50, 5000),
            'overhead_cost' => fake()->randomFloat(4, 10, 500),
            'status' => BomTemplate::STATUS_DRAFT,
            'effective_from' => fake()->optional(0.5)->dateTimeBetween('-6 months', 'now'),
            'effective_to' => null,
            'version' => 1,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => BomTemplate::STATUS_DRAFT]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => BomTemplate::STATUS_ACTIVE,
            'effective_from' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => BomTemplate::STATUS_INACTIVE]);
    }

    public function versioned(int $version): static
    {
        return $this->state(fn () => ['version' => $version]);
    }
}

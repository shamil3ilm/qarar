<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomLine;
use App\Models\Manufacturing\BomTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomLineFactory extends Factory
{
    protected $model = BomLine::class;

    public function definition(): array
    {
        return [
            'bom_template_id' => BomTemplate::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'description' => fake()->optional(0.4)->sentence(),
            'quantity' => fake()->randomFloat(4, 0.5, 100),
            'unit_id' => null,
            'unit_cost' => fake()->randomFloat(4, 1, 500),
            'wastage_percentage' => fake()->randomFloat(2, 0, 10),
            'is_critical' => fake()->boolean(30),
            'warehouse_id' => null,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['is_critical' => true]);
    }

    public function nonCritical(): static
    {
        return $this->state(fn () => ['is_critical' => false]);
    }

    public function atOrder(int $order): static
    {
        return $this->state(fn () => ['line_order' => $order]);
    }

    public function withWastage(float $percentage): static
    {
        return $this->state(fn () => ['wastage_percentage' => $percentage]);
    }
}

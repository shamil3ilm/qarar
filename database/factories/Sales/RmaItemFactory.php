<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\RmaItem;
use App\Models\Sales\RmaRequest;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class RmaItemFactory extends Factory
{
    protected $model = RmaItem::class;

    public function definition(): array
    {
        return [
            'rma_request_id' => RmaRequest::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 50),
            'reason' => fake()->sentence(),
            'description' => fake()->optional(0.3)->sentence(),
            'evidence_paths' => null,
        ];
    }
}

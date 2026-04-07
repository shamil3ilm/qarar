<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockTransferLine;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferLineFactory extends Factory
{
    protected $model = StockTransferLine::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity_sent' => fake()->randomFloat(4, 1, 1000),
            'quantity_received' => fake()->randomFloat(4, 0, 1000),
            'unit_cost' => fake()->randomFloat(4, 1, 5000),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

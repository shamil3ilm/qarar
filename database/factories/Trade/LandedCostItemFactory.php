<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\LandedCostItem;
use App\Models\Trade\LandedCostVoucher;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandedCostItemFactory extends Factory
{
    protected $model = LandedCostItem::class;

    public function definition(): array
    {
        return [
            'voucher_id' => LandedCostVoucher::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 10000),
            'purchase_value' => fake()->randomFloat(4, 100, 500000),
            'weight_kg' => fake()->randomFloat(4, 0.1, 50000),
            'volume_cbm' => fake()->optional(0.3)->randomFloat(4, 0.01, 100),
            'allocated_customs_duty' => 0,
            'allocated_freight' => 0,
            'allocated_insurance' => 0,
            'allocated_clearing' => 0,
            'allocated_other' => 0,
            'total_additional_cost' => 0,
            'total_landed_cost' => fake()->randomFloat(4, 100, 500000),
            'landed_cost_per_unit' => fake()->randomFloat(4, 1, 1000),
        ];
    }
}

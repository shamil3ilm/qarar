<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\LandedCostCharge;
use App\Models\Trade\LandedCostVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandedCostChargeFactory extends Factory
{
    protected $model = LandedCostCharge::class;

    public function definition(): array
    {
        return [
            'voucher_id' => LandedCostVoucher::factory(),
            'charge_type' => fake()->randomElement(['customs_duty', 'freight', 'insurance', 'clearing', 'other']),
            'description' => fake()->sentence(),
            'vendor_id' => null,
            'bill_id' => null,
            'amount' => fake()->randomFloat(4, 100, 50000),
            'currency_code' => fake()->randomElement(['SAR', 'USD']),
            'exchange_rate' => '1.00000000',
            'base_amount' => fake()->randomFloat(4, 100, 50000),
            'account_id' => null,
            'is_allocated' => false,
        ];
    }
}

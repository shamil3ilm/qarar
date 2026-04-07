<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\LandedCostVoucher;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandedCostVoucherFactory extends Factory
{
    protected $model = LandedCostVoucher::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'voucher_number' => 'LCV-' . fake()->unique()->numerify('######'),
            'voucher_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'purchase_order_id' => null,
            'shipment_id' => null,
            'bill_id' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'USD']),
            'exchange_rate' => '1.00000000',
            'total_purchase_value' => fake()->randomFloat(4, 1000, 500000),
            'total_additional_charges' => fake()->randomFloat(4, 100, 50000),
            'total_landed_cost' => fake()->randomFloat(4, 1100, 550000),
            'allocation_method' => fake()->randomElement(['by_value', 'by_quantity', 'by_weight', 'by_volume']),
            'status' => fake()->randomElement(['draft', 'allocated', 'posted', 'cancelled']),
            'journal_entry_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}

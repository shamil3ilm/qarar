<?php

declare(strict_types=1);

namespace Database\Factories\Compliance;

use App\Models\Compliance\BahrainVatReturn;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BahrainVatReturnFactory extends Factory
{
    protected $model = BahrainVatReturn::class;

    public function definition(): array
    {
        $supplies  = $this->faker->randomFloat(2, 10_000, 500_000);
        $purchases = $this->faker->randomFloat(2, 5_000, 200_000);
        $outputVat = round($supplies * 0.10, 4);
        $inputVat  = round($purchases * 0.10, 4);
        $net       = round($outputVat - $inputVat, 4);

        return [
            'organization_id'          => Organization::factory(),
            'period_type'              => 'quarterly',
            'period_quarter'           => 1,
            'period_month'             => null,
            'period_year'              => 2025,
            'period_start'             => '2025-01-01',
            'period_end'               => '2025-03-31',
            'standard_rated_supplies'  => $supplies,
            'zero_rated_supplies'      => 0.0,
            'exempt_supplies'          => 0.0,
            'output_vat'               => $outputVat,
            'standard_rated_purchases' => $purchases,
            'capital_goods_input_tax'  => 0.0,
            'total_input_vat'          => $inputVat,
            'net_vat_payable'          => $net,
            'vat_rate'                 => 10.0,
            'status'                   => BahrainVatReturn::STATUS_DRAFT,
            'filing_due_date'          => '2025-04-30',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => BahrainVatReturn::STATUS_SUBMITTED]);
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'standard_rated_supplies'  => 50_000.0,
            'output_vat'               => 5_000.0,
            'standard_rated_purchases' => 200_000.0,
            'total_input_vat'          => 20_000.0,
            'net_vat_payable'          => -15_000.0,
        ]);
    }
}

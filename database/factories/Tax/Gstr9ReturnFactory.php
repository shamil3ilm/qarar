<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\Gstr9Return;
use Illuminate\Database\Eloquent\Factories\Factory;

class Gstr9ReturnFactory extends Factory
{
    protected $model = Gstr9Return::class;

    public function definition(): array
    {
        $taxable = $this->faker->randomFloat(2, 500_000, 10_000_000);
        $cgst    = round($taxable * 0.09, 2);
        $sgst    = round($taxable * 0.09, 2);
        $itc     = round($cgst + $sgst, 2);
        $netItc  = round($itc * 0.9, 2); // 10% reversal

        return [
            'organization_id'        => Organization::factory(),
            'gstin'                  => strtoupper($this->faker->bothify('##?????####?#Z#')),
            'financial_year_start'   => 2024,
            't4a_taxable_supplies'   => $taxable,
            't4b_zero_rated'         => 0.0,
            't4c_nil_rated'          => 0.0,
            't9_igst_payable'        => 0.0,
            't9_cgst_payable'        => $cgst,
            't9_sgst_payable'        => $sgst,
            't9_cess_payable'        => 0.0,
            't9_igst_paid'           => 0.0,
            't9_cgst_paid'           => $cgst,
            't9_sgst_paid'           => $sgst,
            't9_cess_paid'           => 0.0,
            't6a_itc_inputs'         => $itc,
            't6b_itc_input_services' => 0.0,
            't6c_itc_capital_goods'  => 0.0,
            't6_total_itc'           => $itc,
            't7_itc_reversed'        => round($itc * 0.10, 2),
            'net_itc'                => $netItc,
            't18_late_fee_cgst'      => 0.0,
            't18_late_fee_sgst'      => 0.0,
            'status'                 => Gstr9Return::STATUS_DRAFT,
            'due_date'               => '2025-12-31',
        ];
    }

    public function filed(): static
    {
        return $this->state(fn () => [
            'status'     => Gstr9Return::STATUS_FILED,
            'filed_date' => now()->toDateString(),
            'gstn_arn'   => 'AA2724250012345',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\IndiaEInvoiceSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndiaEInvoiceSubmissionFactory extends Factory
{
    protected $model = IndiaEInvoiceSubmission::class;

    public function definition(): array
    {
        $taxable = $this->faker->randomFloat(2, 10_000, 500_000);
        $cgst    = round($taxable * 0.09, 2);
        $sgst    = round($taxable * 0.09, 2);
        $total   = round($taxable + $cgst + $sgst, 2);

        return [
            'organization_id'  => Organization::factory(),
            'document_number'  => 'INV-' . strtoupper($this->faker->bothify('##??-####')),
            'document_type'    => IndiaEInvoiceSubmission::DOC_TYPE_INVOICE,
            'document_date'    => $this->faker->date('Y-m-d', 'now'),
            'gstin_seller'     => strtoupper($this->faker->bothify('##?????####?#Z#')),
            'gstin_buyer'      => strtoupper($this->faker->bothify('##?????####?#Z#')),
            'seller_name'      => $this->faker->company(),
            'buyer_name'       => $this->faker->company(),
            'seller_state_code' => '27',  // Maharashtra
            'buyer_state_code'  => '07',  // Delhi
            'taxable_value'    => $taxable,
            'cgst_amount'      => $cgst,
            'sgst_amount'      => $sgst,
            'igst_amount'      => 0.0,
            'cess_amount'      => 0.0,
            'total_amount'     => $total,
            'status'           => IndiaEInvoiceSubmission::STATUS_PENDING,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'         => IndiaEInvoiceSubmission::STATUS_ACCEPTED,
            'irn'            => hash('sha256', $this->faker->uuid()),
            'irp_ack_number' => $this->faker->numerify('11##############'),
            'irp_ack_date'   => now(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => IndiaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);
    }
}

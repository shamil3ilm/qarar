<?php

declare(strict_types=1);

namespace Database\Factories\Compliance;

use App\Models\Compliance\FtaEInvoiceSubmission;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FtaEInvoiceSubmissionFactory extends Factory
{
    protected $model = FtaEInvoiceSubmission::class;

    public function definition(): array
    {
        $subtotal  = $this->faker->randomFloat(2, 100, 50000);
        $taxAmount = round($subtotal * 0.05, 2);
        $total     = round($subtotal + $taxAmount, 2);

        return [
            'organization_id' => Organization::factory(),
            'invoice_number'  => 'INV-' . strtoupper($this->faker->bothify('??-####')),
            'invoice_type'    => FtaEInvoiceSubmission::TYPE_INVOICE,
            'issue_date'      => $this->faker->date(),
            'currency_code'   => 'AED',
            'seller_trn'      => $this->faker->numerify('###############'),
            'buyer_trn'       => $this->faker->numerify('###############'),
            'subtotal'        => $subtotal,
            'tax_amount'      => $taxAmount,
            'total_amount'    => $total,
            'tax_rate'        => 5.0,
            'status'          => FtaEInvoiceSubmission::STATUS_PENDING,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => FtaEInvoiceSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'           => FtaEInvoiceSubmission::STATUS_ACCEPTED,
            'submitted_at'     => now()->subHour(),
            'acknowledged_at'  => now(),
        ]);
    }
}

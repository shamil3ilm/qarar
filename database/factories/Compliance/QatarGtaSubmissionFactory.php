<?php

declare(strict_types=1);

namespace Database\Factories\Compliance;

use App\Models\Compliance\QatarGtaSubmission;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class QatarGtaSubmissionFactory extends Factory
{
    protected $model = QatarGtaSubmission::class;

    public function definition(): array
    {
        $subtotal  = $this->faker->randomFloat(2, 100, 50000);
        $taxAmount = 0.0; // Qatar 0% standard rate
        $total     = $subtotal + $taxAmount;

        return [
            'organization_id' => Organization::factory(),
            'invoice_number'  => 'QA-INV-' . strtoupper($this->faker->bothify('??-####')),
            'invoice_type'    => QatarGtaSubmission::TYPE_INVOICE,
            'issue_date'      => $this->faker->date(),
            'currency_code'   => 'QAR',
            'seller_trn'      => $this->faker->numerify('###########'),
            'buyer_trn'       => $this->faker->numerify('###########'),
            'subtotal'        => $subtotal,
            'tax_amount'      => $taxAmount,
            'total_amount'    => $total,
            'status'          => QatarGtaSubmission::STATUS_PENDING,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => QatarGtaSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }
}

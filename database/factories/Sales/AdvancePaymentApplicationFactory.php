<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvancePaymentApplicationFactory extends Factory
{
    protected $model = AdvancePaymentApplication::class;

    public function definition(): array
    {
        return [
            'advance_payment_id' => AdvancePayment::factory(),
            'invoice_id' => Invoice::factory(),
            'bill_id' => null,
            'amount' => fake()->randomFloat(2, 100, 10000),
            'applied_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}

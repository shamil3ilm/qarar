<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PaymentAllocation;
use App\Models\Sales\PaymentReceived;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_received_id' => PaymentReceived::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(4, 10, 50000),
            'base_amount' => fake()->randomFloat(4, 10, 50000),
            'allocated_at' => now(),
        ];
    }
}

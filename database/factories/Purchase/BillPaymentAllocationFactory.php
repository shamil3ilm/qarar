<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Purchase\BillPaymentAllocation;
use App\Models\Purchase\PaymentMade;
use App\Models\Purchase\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillPaymentAllocationFactory extends Factory
{
    protected $model = BillPaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_made_id' => PaymentMade::factory(),
            'bill_id' => Bill::factory(),
            'amount' => fake()->randomFloat(4, 10, 50000),
            'base_amount' => fake()->randomFloat(4, 10, 50000),
            'allocated_at' => now(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\Refund;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'refund_number' => 'RFD-' . fake()->unique()->numerify('######'),
            'refund_type' => fake()->randomElement(['full', 'partial']),
            'refundable_type' => 'App\Models\Sales\Invoice',
            'refundable_id' => fake()->numberBetween(1, 1000),
            'contact_id' => Contact::factory(),
            'sales_return_id' => null,
            'payment_received_id' => null,
            'amount' => fake()->randomFloat(2, 10, 50000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'refund_method' => fake()->randomElement(['bank_transfer', 'cash', 'card', 'credit_note']),
            'refund_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reason' => fake()->sentence(),
            'notes' => fake()->optional(0.3)->sentence(),
            'bank_account_id' => null,
            'transaction_reference' => fake()->optional(0.3)->bothify('TXN-####'),
            'status' => fake()->randomElement(['pending', 'approved', 'processed', 'rejected', 'cancelled']),
            'journal_entry_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'processed_by' => null,
            'processed_at' => null,
            'created_by' => null,
        ];
    }
}

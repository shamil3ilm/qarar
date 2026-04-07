<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingInvoice;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingInvoiceFactory extends Factory
{
    protected $model = BillingInvoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'BINV-' . fake()->unique()->numerify('######'),
            'organization_id' => Organization::factory(),
            'subscription_id' => null,
            'billing_period_start' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'billing_period_end' => fake()->dateTimeBetween('-1 month', 'now'),
            'invoice_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'currency_code' => 'USD',
            'subtotal' => fake()->randomFloat(2, 50, 5000),
            'discount_amount' => fake()->randomFloat(2, 0, 100),
            'tax_amount' => fake()->randomFloat(2, 0, 500),
            'total' => fake()->randomFloat(2, 50, 5000),
            'amount_paid' => 0,
            'amount_due' => fake()->randomFloat(2, 50, 5000),
            'status' => fake()->randomElement(['draft', 'sent', 'paid', 'overdue', 'void']),
            'sent_at' => null,
            'paid_at' => null,
            'pdf_path' => null,
            'notes' => null,
        ];
    }
}
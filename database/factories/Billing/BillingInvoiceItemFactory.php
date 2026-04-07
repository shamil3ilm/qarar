<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingInvoiceItem;
use App\Models\Billing\BillingInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingInvoiceItemFactory extends Factory
{
    protected $model = BillingInvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => BillingInvoice::factory(),
            'item_type' => fake()->randomElement(['subscription', 'addon', 'usage', 'one_time']),
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_label' => fake()->randomElement(['user', 'month', 'GB', 'unit']),
            'unit_price' => fake()->randomFloat(4, 5, 500),
            'discount_amount' => 0,
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'tax_amount' => fake()->randomFloat(2, 0, 50),
            'total' => fake()->randomFloat(2, 5, 500),
            'plan_id' => null,
            'addon_id' => null,
            'metric_type' => null,
            'line_order' => fake()->numberBetween(1, 10),
        ];
    }
}
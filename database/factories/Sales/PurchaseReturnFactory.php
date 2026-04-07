<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PurchaseReturn;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'return_number' => 'PR-' . fake()->unique()->numerify('######'),
            'supplier_id' => Contact::factory(),
            'bill_id' => null,
            'purchase_order_id' => null,
            'return_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'return_reason_id' => null,
            'reason_notes' => fake()->optional(0.5)->sentence(),
            'return_type' => fake()->randomElement(['full', 'partial']),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'subtotal' => fake()->randomFloat(2, 100, 50000),
            'tax_amount' => fake()->randomFloat(2, 0, 5000),
            'total' => fake()->randomFloat(2, 100, 55000),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'shipped', 'completed', 'cancelled']),
            'resolution_type' => fake()->randomElement(['debit_note', 'replacement', 'refund']),
            'debit_note_id' => null,
            'replacement_po_id' => null,
            'shipping_method' => fake()->optional(0.3)->randomElement(['courier', 'pickup']),
            'tracking_number' => fake()->optional(0.3)->bothify('TRK-########'),
            'shipped_at' => null,
            'supplier_received_at' => null,
            'journal_entry_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\DebitNote;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitNoteFactory extends Factory
{
    protected $model = DebitNote::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'debit_note_number' => 'DN-' . fake()->unique()->numerify('######'),
            'bill_id' => null,
            'supplier_id' => Contact::factory(),
            'debit_note_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'exchange_rate' => '1.000000',
            'subtotal' => fake()->randomFloat(2, 100, 50000),
            'tax_amount' => fake()->randomFloat(2, 0, 5000),
            'total' => fake()->randomFloat(2, 100, 55000),
            'applied_amount' => 0,
            'available_amount' => fake()->randomFloat(2, 100, 55000),
            'reason' => fake()->sentence(),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => fake()->randomElement(['draft', 'approved', 'applied', 'cancelled']),
            'journal_entry_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => null,
        ];
    }
}

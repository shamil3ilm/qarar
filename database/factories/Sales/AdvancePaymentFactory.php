<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\AdvancePayment;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvancePaymentFactory extends Factory
{
    protected $model = AdvancePayment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'payment_number' => 'AP-' . fake()->unique()->numerify('######'),
            'payment_type' => fake()->randomElement(['customer', 'supplier']),
            'contact_id' => Contact::factory(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'applied_amount' => 0,
            'available_amount' => fake()->randomFloat(2, 100, 50000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'exchange_rate' => '1.000000',
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'payment_method' => fake()->randomElement(['bank_transfer', 'cash', 'cheque', 'card']),
            'bank_account_id' => null,
            'reference' => fake()->optional(0.4)->bothify('REF-####'),
            'notes' => fake()->optional(0.3)->sentence(),
            'status' => fake()->randomElement(['active', 'fully_applied', 'cancelled']),
            'journal_entry_id' => null,
            'created_by' => null,
        ];
    }
}

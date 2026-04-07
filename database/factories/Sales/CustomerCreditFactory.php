<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CustomerCredit;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Contact::factory(),
            'source_type' => fake()->randomElement(['credit_note', 'overpayment', 'manual']),
            'source_id' => null,
            'original_amount' => fake()->randomFloat(4, 100, 50000),
            'remaining_amount' => fake()->randomFloat(4, 0, 50000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'credit_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }
}

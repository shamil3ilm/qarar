<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\RmaRequest;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class RmaRequestFactory extends Factory
{
    protected $model = RmaRequest::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'rma_number' => 'RMA-' . fake()->unique()->numerify('######'),
            'rma_type' => fake()->randomElement(['customer', 'supplier']),
            'customer_id' => Contact::factory(),
            'supplier_id' => null,
            'invoice_id' => null,
            'bill_id' => null,
            'description' => fake()->sentence(),
            'requested_resolution' => fake()->randomElement(['refund', 'replacement', 'repair', 'credit']),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'in_progress', 'completed']),
            'request_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'expiry_date' => fake()->optional(0.5)->dateTimeBetween('+1 week', '+3 months'),
            'sales_return_id' => null,
            'purchase_return_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => null,
            'created_by' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(4, 500, 50000);
        $taxAmount = round($subtotal * 0.15, 4);
        $total = round($subtotal + $taxAmount, 4);
        $orderDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'order_number' => 'PO-' . fake()->unique()->numerify('######'),
            'supplier_id' => null,
            'supplier_name' => fake()->company(),
            'supplier_email' => fake()->companyEmail(),
            'supplier_address' => fake()->address(),
            'warehouse_id' => null,
            'delivery_address' => fake()->optional(0.6)->address(),
            'order_date' => $orderDate,
            'expected_delivery_date' => fake()->dateTimeBetween($orderDate, '+2 months'),
            'delivery_date' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'exchange_rate' => '1.00000000',
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => $taxAmount,
            'total' => $total,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'requested_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'terms_and_conditions' => fake()->optional(0.2)->paragraph(),
            'reference' => fake()->optional(0.4)->bothify('REF-####'),
            'version' => 1,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrder::STATUS_CONFIRMED,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'delivery_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrder::STATUS_CANCELLED,
        ]);
    }

    public function withDiscount(string $type = 'percentage', float $value = 10.0): static
    {
        return $this->state(function (array $attributes) use ($type, $value) {
            $subtotal = (float) $attributes['subtotal'];
            $discountAmount = $type === 'percentage'
                ? round($subtotal * ($value / 100), 4)
                : round($value, 4);
            $taxAmount = round(($subtotal - $discountAmount) * 0.15, 4);
            $total = round($subtotal - $discountAmount + $taxAmount, 4);

            return [
                'discount_type' => $type,
                'discount_value' => round($value, 4),
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ];
        });
    }
}

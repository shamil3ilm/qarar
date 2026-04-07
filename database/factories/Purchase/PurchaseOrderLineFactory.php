<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Purchase\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 100);
        $unitPrice = fake()->randomFloat(4, 10, 5000);
        $subtotal = round($quantity * $unitPrice, 4);
        $taxRate = fake()->randomElement([0, 5, 15, 18]);
        $taxAmount = round($subtotal * ($taxRate / 100), 4);
        $total = round($subtotal + $taxAmount, 4);

        return [
            'purchase_order_id' => null,
            'product_id' => null,
            'variant_id' => null,
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'quantity_received' => '0.0000',
            'quantity_billed' => '0.0000',
            'unit_id' => null,
            'unit_price' => $unitPrice,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_category_id' => null,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
            'warehouse_id' => null,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function withDiscount(string $type = 'percentage', float $value = 5.0): static
    {
        return $this->state(function (array $attributes) use ($type, $value) {
            $gross = (float) $attributes['quantity'] * (float) $attributes['unit_price'];
            $discountAmount = $type === 'percentage'
                ? round($gross * ($value / 100), 4)
                : round($value, 4);
            $subtotal = round($gross - $discountAmount, 4);
            $taxRate = (float) $attributes['tax_rate'];
            $taxAmount = round($subtotal * ($taxRate / 100), 4);
            $total = round($subtotal + $taxAmount, 4);

            return [
                'discount_type' => $type,
                'discount_value' => round($value, 4),
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ];
        });
    }

    public function fullyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $attributes['quantity'],
        ]);
    }

    public function fullyBilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $attributes['quantity'],
            'quantity_billed' => $attributes['quantity'],
        ]);
    }

    public function partiallyReceived(float $fraction = 0.5): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => round((float) $attributes['quantity'] * $fraction, 4),
        ]);
    }
}

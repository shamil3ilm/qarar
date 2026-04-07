<?php

declare(strict_types=1);

namespace Database\Factories\Purchase;

use App\Models\Purchase\BillLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillLine>
 */
class BillLineFactory extends Factory
{
    protected $model = BillLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 100);
        $unitPrice = fake()->randomFloat(4, 10, 5000);
        $subtotal = round($quantity * $unitPrice, 4);
        $taxRate = fake()->randomElement([0, 5, 15, 18]);
        $taxAmount = round($subtotal * ($taxRate / 100), 4);
        $total = round($subtotal + $taxAmount, 4);

        return [
            'bill_id' => null,
            'product_id' => null,
            'variant_id' => null,
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit_id' => null,
            'unit_price' => $unitPrice,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_category_id' => null,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_code' => fake()->optional(0.3)->lexify('TAX-???'),
            'cgst_rate' => '0.0000',
            'cgst_amount' => '0.0000',
            'sgst_rate' => '0.0000',
            'sgst_amount' => '0.0000',
            'igst_rate' => '0.0000',
            'igst_amount' => '0.0000',
            'hsn_code' => fake()->optional(0.4)->numerify('########'),
            'subtotal' => $subtotal,
            'total' => $total,
            'account_id' => null,
            'warehouse_id' => null,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function withGst(float $rate = 18.0): static
    {
        return $this->state(function (array $attributes) use ($rate) {
            $subtotal = (float) $attributes['subtotal'];
            $halfRate = round($rate / 2, 4);
            $cgstAmount = round($subtotal * ($halfRate / 100), 4);
            $sgstAmount = round($subtotal * ($halfRate / 100), 4);
            $taxAmount = round($cgstAmount + $sgstAmount, 4);
            $total = round($subtotal + $taxAmount, 4);

            return [
                'tax_rate' => $rate,
                'tax_amount' => $taxAmount,
                'cgst_rate' => $halfRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $halfRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => '0.0000',
                'igst_amount' => '0.0000',
                'total' => $total,
            ];
        });
    }

    public function withIgst(float $rate = 18.0): static
    {
        return $this->state(function (array $attributes) use ($rate) {
            $subtotal = (float) $attributes['subtotal'];
            $igstAmount = round($subtotal * ($rate / 100), 4);
            $total = round($subtotal + $igstAmount, 4);

            return [
                'tax_rate' => $rate,
                'tax_amount' => $igstAmount,
                'cgst_rate' => '0.0000',
                'cgst_amount' => '0.0000',
                'sgst_rate' => '0.0000',
                'sgst_amount' => '0.0000',
                'igst_rate' => $rate,
                'igst_amount' => $igstAmount,
                'total' => $total,
            ];
        });
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
}

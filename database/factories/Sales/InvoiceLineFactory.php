<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 100);
        $unitPrice = fake()->randomFloat(4, 10, 5000);
        $subtotal = round($quantity * $unitPrice, 4);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $taxAmount = round($subtotal * $taxRate / 100, 4);
        $total = round($subtotal + $taxAmount, 4);

        return [
            'invoice_id' => fake()->randomNumber(3, true),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => null,
            'discount_value' => '0.0000',
            'discount_amount' => '0.0000',
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_code' => fake()->optional(0.5)->randomElement(['VAT', 'GST', 'IGST']),
            'cgst_rate' => '0.0000',
            'cgst_amount' => '0.0000',
            'sgst_rate' => '0.0000',
            'sgst_amount' => '0.0000',
            'igst_rate' => '0.0000',
            'igst_amount' => '0.0000',
            'hsn_code' => fake()->optional(0.3)->numerify('########'),
            'subtotal' => $subtotal,
            'total' => $total,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function withPercentageDiscount(float $percent = 10): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $gross = (float) $attributes['quantity'] * (float) $attributes['unit_price'];
            $discountAmount = round($gross * $percent / 100, 4);
            $subtotal = round($gross - $discountAmount, 4);
            $taxAmount = round($subtotal * (float) $attributes['tax_rate'] / 100, 4);
            $total = round($subtotal + $taxAmount, 4);

            return [
                'discount_type' => 'percentage',
                'discount_value' => $percent,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ];
        });
    }

    public function withGst(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = (float) $attributes['subtotal'];
            $cgstRate = 9.0;
            $sgstRate = 9.0;
            $cgstAmount = round($subtotal * $cgstRate / 100, 4);
            $sgstAmount = round($subtotal * $sgstRate / 100, 4);
            $taxAmount = round($cgstAmount + $sgstAmount, 4);

            return [
                'tax_rate' => 18.0,
                'tax_amount' => $taxAmount,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => '0.0000',
                'igst_amount' => '0.0000',
                'total' => round($subtotal + $taxAmount, 4),
            ];
        });
    }
}

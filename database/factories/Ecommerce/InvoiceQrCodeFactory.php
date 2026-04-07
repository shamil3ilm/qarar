<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\InvoiceQrCode;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceQrCodeFactory extends Factory
{
    protected $model = InvoiceQrCode::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'qr_type' => fake()->randomElement(['zatca', 'payment', 'info']),
            'qr_data' => fake()->sha256(),
            'qr_image_path' => 'qr/' . fake()->uuid() . '.png',
            'payment_link' => fake()->optional(0.3)->url(),
            'payment_amount' => fake()->optional(0.3)->randomFloat(2, 100, 10000),
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 day', '+30 days'),
        ];
    }
}

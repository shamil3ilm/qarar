<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Core\Organization;
use App\Models\Ecommerce\EcommerceChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EcommerceOrderFactory extends Factory
{
    protected $model = EcommerceOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel_id' => EcommerceChannel::factory(),
            'external_order_id' => fake()->unique()->numerify('ORD-######'),
            'order_number' => fake()->unique()->numerify('WEB-######'),
            'status' => fake()->randomElement(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled']),
            'financial_status' => fake()->randomElement(['pending', 'paid', 'refunded', 'partially_refunded']),
            'fulfillment_status' => fake()->randomElement(['unfulfilled', 'partial', 'fulfilled']),
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_id' => null,
            'shipping_address' => null,
            'billing_address' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'USD']),
            'subtotal' => fake()->randomFloat(2, 50, 5000),
            'discount_amount' => 0,
            'shipping_amount' => fake()->randomFloat(2, 0, 100),
            'tax_amount' => fake()->randomFloat(2, 0, 500),
            'total_amount' => fake()->randomFloat(2, 50, 5500),
            'shipping_method' => fake()->optional(0.5)->randomElement(['standard', 'express']),
        ];
    }
}

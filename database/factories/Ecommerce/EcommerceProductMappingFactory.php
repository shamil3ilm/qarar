<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceProductMapping;
use App\Models\Ecommerce\EcommerceChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EcommerceProductMappingFactory extends Factory
{
    protected $model = EcommerceProductMapping::class;

    public function definition(): array
    {
        return [
            'channel_id' => EcommerceChannel::factory(),
            'product_id' => null,
            'external_product_id' => fake()->numerify('EP-######'),
            'external_variant_id' => fake()->optional(0.5)->numerify('EV-######'),
            'external_sku' => strtoupper(fake()->bothify('SKU-####')),
            'sync_enabled' => true,
            'last_sync_at' => fake()->optional(0.5)->dateTimeBetween('-1 week', 'now'),
        ];
    }
}

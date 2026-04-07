<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EcommerceChannelFactory extends Factory
{
    protected $model = EcommerceChannel::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company() . ' Store',
            'platform' => fake()->randomElement(['shopify', 'woocommerce', 'magento', 'salla', 'zid']),
            'platform_name' => fake()->randomElement(['Shopify', 'WooCommerce', 'Magento', 'Salla', 'Zid']),
            'store_url' => fake()->url(),
            'credentials' => null,
            'settings' => null,
            'default_warehouse_id' => null,
            'default_customer_id' => null,
            'sync_products' => true,
            'sync_orders' => true,
            'sync_inventory' => true,
            'auto_fulfill' => false,
            'last_sync_at' => null,
            'status' => fake()->randomElement(['active', 'inactive', 'error']),
        ];
    }
}

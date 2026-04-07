<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceSyncLog;
use App\Models\Ecommerce\EcommerceChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EcommerceSyncLogFactory extends Factory
{
    protected $model = EcommerceSyncLog::class;

    public function definition(): array
    {
        return [
            'channel_id' => EcommerceChannel::factory(),
            'sync_type' => fake()->randomElement(['products', 'orders', 'inventory']),
            'direction' => fake()->randomElement(['pull', 'push']),
            'status' => fake()->randomElement(['started', 'completed', 'failed']),
            'total_records' => fake()->numberBetween(0, 500),
            'processed_records' => fake()->numberBetween(0, 500),
            'failed_records' => fake()->numberBetween(0, 10),
            'errors' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }
}

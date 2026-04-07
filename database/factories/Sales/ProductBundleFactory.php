<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\ProductBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleFactory extends Factory
{
    protected $model = ProductBundle::class;

    public function definition(): array
    {
        $originalTotal = fake()->randomFloat(2, 100, 5000);
        $discountPercent = fake()->randomFloat(2, 5, 30);
        $savingsAmount = round($originalTotal * $discountPercent / 100, 2);
        $bundlePrice = round($originalTotal - $savingsAmount, 2);

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Bundle',
            'sku' => strtoupper(fake()->unique()->bothify('BDL-????####')),
            'description' => fake()->sentence(),
            'image_path' => null,
            'pricing_type' => fake()->randomElement(['fixed', 'discount', 'calculated']),
            'bundle_price' => $bundlePrice,
            'discount_percent' => $discountPercent,
            'original_total' => $originalTotal,
            'savings_amount' => $savingsAmount,
            'available_from' => fake()->optional(0.5)->dateTimeBetween('-7 days', 'now'),
            'available_until' => fake()->optional(0.4)->dateTimeBetween('+7 days', '+6 months'),
            'is_limited_time' => fake()->boolean(30),
            'max_quantity' => fake()->optional(0.3)->numberBetween(50, 500),
            'sold_quantity' => fake()->numberBetween(0, 50),
            'min_order_quantity' => 1,
            'max_order_quantity' => fake()->optional(0.4)->numberBetween(5, 20),
            'eligible_customer_tiers' => null,
            'is_featured' => fake()->boolean(20),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => [
            'is_featured' => true,
        ]);
    }

    public function limitedTime(): static
    {
        return $this->state(fn () => [
            'is_limited_time' => true,
            'available_from' => now(),
            'available_until' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'is_limited_time' => true,
            'available_from' => now()->subDays(60),
            'available_until' => now()->subDay(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\RewardsCatalogItem;
use App\Models\Core\Organization;
use App\Models\Loyalty\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class RewardsCatalogItemFactory extends Factory
{
    protected $model = RewardsCatalogItem::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'image_path' => null,
            'reward_type' => fake()->randomElement(['discount', 'free_product', 'voucher', 'experience']),
            'type' => fake()->randomElement(['discount', 'product', 'voucher']),
            'value' => null,
            'points_cost' => fake()->numberBetween(100, 10000),
            'points_required' => fake()->numberBetween(100, 10000),
            'monetary_value' => fake()->optional(0.5)->randomFloat(2, 10, 500),
            'discount_percent' => fake()->optional(0.3)->randomFloat(2, 5, 50),
            'discount_amount' => fake()->optional(0.3)->randomFloat(2, 10, 500),
            'min_order_amount' => fake()->optional(0.3)->randomFloat(2, 50, 500),
            'product_id' => null,
            'stock_quantity' => fake()->optional(0.5)->numberBetween(10, 1000),
            'redeemed_quantity' => 0,
            'max_per_customer' => fake()->optional(0.3)->numberBetween(1, 5),
            'required_tier_code' => null,
            'available_from' => null,
            'available_until' => null,
            'is_featured' => false,
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 50),
        ];
    }
}

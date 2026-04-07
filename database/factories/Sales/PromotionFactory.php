<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Promotion',
            'code' => strtoupper(fake()->unique()->bothify('PROMO-????##')),
            'description' => fake()->sentence(),
            'type' => Promotion::TYPE_PERCENTAGE,
            'apply_to' => Promotion::APPLY_ORDER,
            'target' => Promotion::TARGET_ALL,
            'discount_value' => fake()->randomFloat(4, 5, 50),
            'max_discount_amount' => fake()->optional(0.5)->randomFloat(4, 100, 5000),
            'buy_quantity' => null,
            'get_quantity' => null,
            'get_discount_percent' => null,
            'tiers' => null,
            'min_order_amount' => fake()->optional(0.4)->randomFloat(4, 50, 500),
            'min_quantity' => null,
            'max_uses' => fake()->optional(0.5)->numberBetween(10, 1000),
            'max_uses_per_customer' => fake()->optional(0.4)->numberBetween(1, 10),
            'current_uses' => 0,
            'start_date' => now()->subDays(fake()->numberBetween(0, 30)),
            'end_date' => now()->addDays(fake()->numberBetween(7, 90)),
            'valid_days' => null,
            'valid_time_start' => null,
            'valid_time_end' => null,
            'is_stackable' => false,
            'is_exclusive' => false,
            'priority' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'requires_code' => false,
            'created_by' => User::factory(),
        ];
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => $value,
        ]);
    }

    public function fixedAmount(float $value = 50): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => $value,
        ]);
    }

    public function buyXGetY(int $buyQty = 2, int $getQty = 1, float $discountPercent = 100): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_quantity' => $buyQty,
            'get_quantity' => $getQty,
            'get_discount_percent' => $discountPercent,
        ]);
    }

    public function tiered(array $tiers = null): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_TIERED,
            'tiers' => $tiers ?? [
                ['min_quantity' => 5, 'discount_percent' => 5],
                ['min_quantity' => 10, 'discount_percent' => 10],
                ['min_quantity' => 25, 'discount_percent' => 15],
            ],
        ]);
    }

    public function requiresCode(): static
    {
        return $this->state(fn () => [
            'requires_code' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(1),
        ]);
    }

    public function exclusive(): static
    {
        return $this->state(fn () => [
            'is_exclusive' => true,
            'is_stackable' => false,
        ]);
    }
}

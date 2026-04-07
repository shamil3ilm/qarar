<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\PriceOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceOverrideFactory extends Factory
{
    protected $model = PriceOverride::class;

    public function definition(): array
    {
        $originalPrice = fake()->randomFloat(4, 50, 5000);
        $discountPercent = fake()->randomFloat(2, 5, 30);
        $overridePrice = round($originalPrice * (1 - $discountPercent / 100), 4);
        $priceDifference = round($originalPrice - $overridePrice, 4);
        $quantity = fake()->randomFloat(4, 1, 100);
        $totalImpact = round($priceDifference * $quantity, 2);
        $costPrice = round($originalPrice * 0.6, 4);
        $marginBefore = round(($originalPrice - $costPrice) / $originalPrice * 100, 2);
        $marginAfter = round(($overridePrice - $costPrice) / $overridePrice * 100, 2);

        return [
            'organization_id' => Organization::factory(),
            'created_by' => \App\Models\User::factory(),
            'document_type' => fake()->randomElement(['invoice', 'quotation', 'sales_order']),
            'document_id' => null,
            'line_item_id' => null,
            'product_id' => null,
            'variant_id' => null,
            'original_price' => $originalPrice,
            'override_price' => $overridePrice,
            'cost_price' => $costPrice,
            'price_difference' => $priceDifference,
            'discount_percent' => $discountPercent,
            'quantity' => $quantity,
            'total_impact' => $totalImpact,
            'override_type' => fake()->randomElement(['discount', 'price_change', 'markup']),
            'reason_code' => fake()->randomElement(['bulk_order', 'loyal_customer', 'price_match', 'manager_approval', 'damaged_goods']),
            'reason' => fake()->sentence(),
            'notes' => fake()->optional(0.3)->sentence(),
            'approval_status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'approved_at' => null,
            'approval_notes' => null,
            'policy_id' => null,
            'customer_id' => null,
            'margin_before' => $marginBefore,
            'margin_after' => $marginAfter,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approval_notes' => 'Approved per policy.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'approval_status' => 'rejected',
            'approval_notes' => fake()->sentence(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'approval_status' => 'pending',
            'approved_at' => null,
        ]);
    }
}

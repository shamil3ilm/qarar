<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CampaignTierOffer;
use App\Models\Sales\SeasonalCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignTierOfferFactory extends Factory
{
    protected $model = CampaignTierOffer::class;

    public function definition(): array
    {
        return [
            'campaign_id' => SeasonalCampaign::factory(),
            'tier_code' => fake()->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'tier_name' => fake()->randomElement(['Bronze', 'Silver', 'Gold', 'Platinum']),
            'min_purchase_amount' => fake()->randomFloat(2, 100, 5000),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'max_discount' => fake()->optional(0.5)->randomFloat(2, 50, 500),
            'extra_discount_percent' => fake()->randomFloat(2, 0, 10),
            'bonus_points' => fake()->numberBetween(0, 1000),
            'early_access' => fake()->boolean(30),
            'early_access_hours' => fake()->optional(0.3)->numberBetween(1, 48),
            'description' => fake()->optional(0.5)->sentence(),
            'is_active' => true,
        ];
    }
}

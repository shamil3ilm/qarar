<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\SeasonalCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeasonalCampaignFactory extends Factory
{
    protected $model = SeasonalCampaign::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Ramadan', 'Eid', 'Summer', 'Winter', 'Back to School', 'Year End']) . ' Sale ' . fake()->year(),
            'code' => strtoupper(fake()->unique()->bothify('CAMP-????##')),
            'description' => fake()->paragraph(),
            'campaign_type' => fake()->randomElement(['seasonal', 'flash_sale', 'clearance', 'holiday', 'event']),
            'banner_image' => null,
            'theme_color' => fake()->hexColor(),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'ends_at' => now()->addDays(fake()->numberBetween(15, 60)),
            'is_recurring' => false,
            'recurrence_rule' => null,
            'discount_type' => fake()->randomElement(['percentage', 'fixed_amount']),
            'discount_value' => fake()->randomFloat(2, 5, 40),
            'max_discount' => fake()->optional(0.5)->randomFloat(2, 100, 2000),
            'min_purchase' => fake()->optional(0.4)->randomFloat(2, 50, 500),
            'applies_to' => fake()->randomElement(['all', 'categories', 'products', 'bundles']),
            'applicable_category_ids' => null,
            'applicable_product_ids' => null,
            'applicable_bundle_ids' => null,
            'excluded_product_ids' => null,
            'max_uses' => fake()->optional(0.4)->numberBetween(100, 10000),
            'max_uses_per_customer' => fake()->optional(0.3)->numberBetween(1, 5),
            'times_used' => 0,
            'budget_limit' => fake()->optional(0.3)->randomFloat(2, 5000, 100000),
            'budget_used' => '0.00',
            'promotional_message' => fake()->optional(0.5)->sentence(),
            'send_notification' => fake()->boolean(40),
            'show_countdown' => fake()->boolean(60),
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(37),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
            'recurrence_rule' => 'yearly',
        ]);
    }

    public function flashSale(): static
    {
        return $this->state(fn () => [
            'campaign_type' => 'flash_sale',
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'show_countdown' => true,
        ]);
    }

    public function ramadan(): static
    {
        return $this->state(fn () => [
            'name' => 'Ramadan Sale ' . now()->year,
            'campaign_type' => 'seasonal',
            'is_recurring' => true,
            'recurrence_rule' => 'yearly',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PromotionUsage;
use App\Models\Sales\Promotion;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionUsageFactory extends Factory
{
    protected $model = PromotionUsage::class;

    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'coupon_code_id' => null,
            'contact_id' => Contact::factory(),
            'invoice_id' => null,
            'order_amount' => fake()->randomFloat(2, 50, 10000),
            'discount_amount' => fake()->randomFloat(2, 5, 1000),
            'used_at' => now(),
        ];
    }
}

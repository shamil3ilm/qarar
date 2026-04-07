<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\DiscountCodeUsage;
use App\Models\Billing\DiscountCode;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountCodeUsageFactory extends Factory
{
    protected $model = DiscountCodeUsage::class;

    public function definition(): array
    {
        return [
            'discount_code_id' => DiscountCode::factory(),
            'organization_id' => Organization::factory(),
            'invoice_id' => null,
            'discount_amount' => fake()->randomFloat(2, 5, 500),
        ];
    }
}
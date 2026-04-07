<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\TradeAgreement;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeAgreementFactory extends Factory
{
    protected $model = TradeAgreement::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Agreement',
            'code' => strtoupper(fake()->unique()->lexify('TA-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'member_countries' => [fake()->countryCode(), fake()->countryCode()],
            'effective_from' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'effective_to' => fake()->optional(0.3)->dateTimeBetween('+1 year', '+10 years'),
            'is_active' => true,
        ];
    }
}

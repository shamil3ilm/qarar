<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\PreferentialDutyRate;
use App\Models\Trade\TradeAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreferentialDutyRateFactory extends Factory
{
    protected $model = PreferentialDutyRate::class;

    public function definition(): array
    {
        return [
            'trade_agreement_id' => TradeAgreement::factory(),
            'tariff_code' => fake()->numerify('########'),
            'origin_country' => fake()->countryCode(),
            'destination_country' => fake()->countryCode(),
            'preferential_rate' => fake()->randomFloat(4, 0, 10),
            'normal_rate' => fake()->randomFloat(4, 5, 25),
            'rule_of_origin' => fake()->optional(0.3)->sentence(),
            'effective_from' => fake()->dateTimeBetween('-2 years', 'now'),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}

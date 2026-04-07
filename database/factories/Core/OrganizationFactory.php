<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $countries = ['SA', 'AE', 'QA', 'OM', 'BH', 'KW', 'IN'];
        $countryCode = fake()->randomElement($countries);

        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company() . ' LLC',
            'country_code' => $countryCode,
            'tax_scheme' => $countryCode === 'IN' ? 'GST' : 'VAT',
            'tax_number' => fake()->numerify('###############'),
            'base_currency' => match ($countryCode) {
                'SA' => 'SAR',
                'AE' => 'AED',
                'QA' => 'QAR',
                'OM' => 'OMR',
                'BH' => 'BHD',
                'KW' => 'KWD',
                'IN' => 'INR',
                default => 'USD',
            },
            'fiscal_year_start_month' => 1,
            'fiscal_year_start_day' => 1,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'is_active' => true,
            'activated_at' => now(),
        ];
    }

    public function saudi(): static
    {
        return $this->state(fn () => [
            'country_code' => 'SA',
            'tax_scheme' => 'VAT',
            'base_currency' => 'SAR',
        ]);
    }

    public function indian(): static
    {
        return $this->state(fn () => [
            'country_code' => 'IN',
            'tax_scheme' => 'GST',
            'base_currency' => 'INR',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'suspended_at' => now(),
        ]);
    }
}

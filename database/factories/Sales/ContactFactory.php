<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $currencies = ['SAR', 'AED', 'QAR', 'OMR', 'BHD', 'KWD', 'INR', 'USD'];
        $countryCodes = ['SA', 'AE', 'QA', 'OM', 'BH', 'KW', 'IN', 'US'];

        return [
            'organization_id' => Organization::factory(),
            'contact_type' => fake()->randomElement([
                Contact::TYPE_CUSTOMER,
                Contact::TYPE_SUPPLIER,
                Contact::TYPE_BOTH,
            ]),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'tax_number' => fake()->numerify('###############'),
            'tax_registration_name' => fake()->company() . ' Tax Reg',
            'payment_terms' => fake()->randomElement([7, 15, 30, 45, 60, 90]),
            'credit_limit' => fake()->randomFloat(4, 1000, 100000),
            'currency_code' => fake()->randomElement($currencies),
            'billing_address_line_1' => fake()->streetAddress(),
            'billing_address_line_2' => fake()->optional(0.3)->secondaryAddress(),
            'billing_city' => fake()->city(),
            'billing_state' => fake()->state(),
            'billing_postal_code' => fake()->postcode(),
            'billing_country_code' => fake()->randomElement($countryCodes),
            'shipping_address_line_1' => fake()->streetAddress(),
            'shipping_address_line_2' => fake()->optional(0.3)->secondaryAddress(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country_code' => fake()->randomElement($countryCodes),
            'notes' => fake()->optional(0.4)->sentence(),
            'is_active' => true,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => [
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'lead_number' => strtoupper(fake()->unique()->lexify('LD-####-???')),
            'title' => fake()->optional(0.3)->jobTitle(),
            'lead_type' => fake()->randomElement([Lead::TYPE_INDIVIDUAL, Lead::TYPE_COMPANY]),
            'company_name' => fake()->company(),
            'industry' => fake()->optional(0.6)->randomElement([
                'Technology', 'Healthcare', 'Finance', 'Manufacturing',
                'Retail', 'Education', 'Construction', 'Real Estate',
            ]),
            'website' => fake()->optional(0.4)->url(),
            'employee_count' => fake()->optional(0.5)->numberBetween(10, 5000),
            'annual_revenue' => fake()->optional(0.4)->randomFloat(2, 100000, 50000000),
            'contact_name' => fake()->name(),
            'contact_title' => fake()->optional(0.5)->jobTitle(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->optional(0.5)->phoneNumber(),
            'address_line_1' => fake()->optional(0.6)->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->optional(0.6)->city(),
            'state' => fake()->optional(0.6)->state(),
            'postal_code' => fake()->optional(0.6)->postcode(),
            'country_code' => fake()->randomElement(['SA', 'AE', 'QA', 'IN']),
            'lead_source_id' => null,
            'source_details' => null,
            'assigned_to' => null,
            'branch_id' => null,
            'status' => Lead::STATUS_NEW,
            'lost_reason' => null,
            'lead_score' => fake()->numberBetween(0, 100),
            'rating' => fake()->randomElement([Lead::RATING_HOT, Lead::RATING_WARM, Lead::RATING_COLD]),
            'estimated_value' => fake()->optional(0.5)->randomFloat(4, 1000, 500000),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'converted_contact_id' => null,
            'converted_opportunity_id' => null,
            'converted_at' => null,
            'converted_by' => null,
            'description' => fake()->optional(0.5)->paragraph(),
            'notes' => null,
            'tags' => fake()->optional(0.3)->randomElements(
                ['enterprise', 'sme', 'startup', 'government', 'priority'],
                fake()->numberBetween(1, 3)
            ),
            'created_by' => null,
        ];
    }

    public function statusNew(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_NEW]);
    }

    public function contacted(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_CONTACTED]);
    }

    public function qualified(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_QUALIFIED]);
    }

    public function converted(): static
    {
        return $this->state(fn () => [
            'status' => Lead::STATUS_CONVERTED,
            'converted_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => Lead::STATUS_LOST,
            'lost_reason' => fake()->sentence(),
        ]);
    }

    public function hot(): static
    {
        return $this->state(fn () => [
            'rating' => Lead::RATING_HOT,
            'lead_score' => fake()->numberBetween(75, 100),
        ]);
    }

    public function cold(): static
    {
        return $this->state(fn () => [
            'rating' => Lead::RATING_COLD,
            'lead_score' => fake()->numberBetween(0, 25),
        ]);
    }

    public function company(): static
    {
        return $this->state(fn () => ['lead_type' => Lead::TYPE_COMPANY]);
    }

    public function individual(): static
    {
        return $this->state(fn () => ['lead_type' => Lead::TYPE_INDIVIDUAL]);
    }
}

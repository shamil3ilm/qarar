<?php

declare(strict_types=1);

namespace Database\Factories\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadSourceFactory extends Factory
{
    protected $model = LeadSource::class;

    public function definition(): array
    {
        $sources = [
            'Website', 'Referral', 'Social Media', 'Cold Call',
            'Trade Show', 'Email Campaign', 'Partner', 'Advertisement',
            'Google Ads', 'LinkedIn', 'Direct Walk-In',
        ];

        $name = fake()->unique()->randomElement($sources);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'code' => strtoupper(fake()->lexify('SRC-???')),
            'description' => fake()->optional(0.5)->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

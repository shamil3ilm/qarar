<?php

declare(strict_types=1);

namespace Database\Factories\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(4, 5000, 500000);
        $probability = fake()->numberBetween(10, 90);
        $expectedRevenue = round($amount * ($probability / 100), 4);

        return [
            'organization_id' => Organization::factory(),
            'opportunity_number' => strtoupper(fake()->unique()->lexify('OPP-####-???')),
            'name' => fake()->catchPhrase(),
            'description' => fake()->optional(0.5)->paragraph(),
            'contact_id' => null,
            'lead_id' => null,
            'account_name' => fake()->company(),
            'pipeline_stage_id' => null,
            'probability' => $probability,
            'amount' => $amount,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'expected_revenue' => $expectedRevenue,
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'actual_close_date' => null,
            'status' => Opportunity::STATUS_OPEN,
            'lost_reason' => null,
            'won_reason' => null,
            'assigned_to' => null,
            'branch_id' => null,
            'lead_source_id' => null,
            'quotation_id' => null,
            'sales_order_id' => null,
            'notes' => null,
            'tags' => fake()->optional(0.3)->randomElements(
                ['high-value', 'strategic', 'recurring', 'urgent', 'expansion'],
                fake()->numberBetween(1, 2)
            ),
            'competitors' => null,
            'created_by' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => Opportunity::STATUS_OPEN]);
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'status' => Opportunity::STATUS_WON,
            'probability' => 100,
            'actual_close_date' => now(),
            'won_reason' => fake()->sentence(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => Opportunity::STATUS_LOST,
            'probability' => 0,
            'actual_close_date' => now(),
            'lost_reason' => fake()->sentence(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Opportunity::STATUS_SUSPENDED]);
    }

    public function highValue(): static
    {
        return $this->state(function () {
            $amount = fake()->randomFloat(4, 100000, 1000000);
            $probability = fake()->numberBetween(60, 95);

            return [
                'amount' => $amount,
                'probability' => $probability,
                'expected_revenue' => round($amount * ($probability / 100), 4),
            ];
        });
    }

    public function closingSoon(): static
    {
        return $this->state(fn () => [
            'expected_close_date' => fake()->dateTimeBetween('now', '+7 days'),
        ]);
    }
}

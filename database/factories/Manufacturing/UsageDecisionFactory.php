<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\UsageDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageDecision>
 */
class UsageDecisionFactory extends Factory
{
    protected $model = UsageDecision::class;

    public function definition(): array
    {
        return [
            'organization_id'   => \App\Models\Core\Organization::factory(),
            'inspection_lot_id' => InspectionLot::factory(),
            'decision_number'   => strtoupper(fake()->unique()->lexify('UD-####-???')),
            'decision_code'     => UsageDecision::DECISION_ACCEPT,
            'qty_unrestricted'  => 100,
            'qty_blocked'       => 0,
            'qty_scrap'         => 0,
            'decided_by'        => null,
            'decided_at'        => now(),
        ];
    }
}

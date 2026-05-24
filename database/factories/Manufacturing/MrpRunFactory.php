<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\MrpRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MrpRunFactory extends Factory
{
    protected $model = MrpRun::class;

    public function definition(): array
    {
        return [
            'uuid'                    => (string) Str::uuid(),
            'organization_id'         => Organization::factory(),
            'run_date'                => now(),
            'planning_horizon_days'   => 30,
            'status'                  => MrpRun::STATUS_COMPLETED,
            'total_products_analyzed' => 0,
            'total_planned_orders'    => 0,
            'error_message'           => null,
            'run_by'                  => User::factory(),
            'completed_at'            => now(),
        ];
    }
}

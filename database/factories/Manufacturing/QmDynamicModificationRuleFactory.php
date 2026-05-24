<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\QmDynamicModificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QmDynamicModificationRuleFactory extends Factory
{
    protected $model = QmDynamicModificationRule::class;

    public function definition(): array
    {
        return [
            'organization_id'                => Organization::factory(),
            'rule_code'                      => strtoupper(fake()->unique()->bothify('DMR-???')),
            'name'                           => fake()->words(3, true),
            'description'                    => null,
            'tighten_consecutive_fails'      => 2,
            'reduce_after_consecutive_pass'  => 5,
            'skip_after_reduced_pass'        => 3,
            'reinstate_after_tightened_fail' => 1,
            'is_active'                      => true,
            'created_by'                     => User::factory(),
        ];
    }
}

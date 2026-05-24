<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\CapaEightD;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CapaEightDFactory extends Factory
{
    protected $model = CapaEightD::class;

    public function definition(): array
    {
        return [
            'organization_id'       => Organization::factory(),
            'capa_number'           => strtoupper(fake()->unique()->bothify('8D-####-??')),
            'title'                 => fake()->sentence(5),
            'status'                => CapaEightD::STATUS_D0_OPEN,
            'source_complaint_id'   => null,
            'source_type'           => null,
            'created_by'            => User::factory(),
            'd0_emergency_response' => null,
            'd0_date'               => null,
        ];
    }
}

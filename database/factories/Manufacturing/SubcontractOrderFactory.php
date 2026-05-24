<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubcontractOrderFactory extends Factory
{
    protected $model = SubcontractOrder::class;

    public function definition(): array
    {
        return [
            'uuid'                   => (string) Str::uuid(),
            'organization_id'        => Organization::factory(),
            'order_number'           => strtoupper(fake()->unique()->bothify('SCO-####-??')),
            'contact_id'             => Contact::factory(),
            'status'                 => SubcontractOrder::STATUS_DRAFT,
            'issued_date'            => now()->format('Y-m-d'),
            'expected_receipt_date'  => now()->addDays(14)->format('Y-m-d'),
            'currency_code'          => 'USD',
            'service_charge'         => 0,
            'notes'                  => null,
            'purchase_order_id'      => null,
            'created_by'             => User::factory(),
            'branch_id'              => null,
        ];
    }
}

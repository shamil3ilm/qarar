<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\CertificateOfAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateOfAnalysisFactory extends Factory
{
    protected $model = CertificateOfAnalysis::class;

    public function definition(): array
    {
        return [
            'uuid'               => (string) Str::uuid(),
            'organization_id'    => Organization::factory(),
            'certificate_number' => strtoupper(fake()->unique()->bothify('COA-####-???')),
            'product_id'         => Product::factory(),
            'batch_number'       => fake()->optional()->bothify('BATCH-####'),
            'inspection_lot_id'  => null,
            'contact_id'         => null,
            'issue_date'         => now()->toDateString(),
            'test_date'          => now()->toDateString(),
            'test_results'       => [
                [
                    'parameter'     => 'Purity',
                    'specification' => '>=99%',
                    'result'        => '99.5%',
                    'unit'          => '%',
                    'pass_fail'     => 'pass',
                ],
            ],
            'overall_result'     => 'pass',
            'remarks'            => null,
            'issued_by'          => User::factory(),
            'approved_by'        => null,
            'status'             => 'draft',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }
}

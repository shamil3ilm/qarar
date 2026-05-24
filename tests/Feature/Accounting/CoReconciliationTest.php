<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CoAssessmentCycle;
use App\Models\Accounting\CoReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CoReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.co.view',
            'accounting.co.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRun(array $overrides = []): CoReconciliationRun
    {
        return CoReconciliationRun::create(array_merge([
            'organization_id' => $this->organization->id,
            'run_number'      => 'KALC-' . fake()->unique()->numerify('####'),
            'source_type'     => 'assessment',
            'source_id'       => 1,
            'fiscal_year'     => '2025',
            'period'          => '01',
            'status'          => 'posted',
            'total_amount'    => 50000.00,
            'currency'        => 'SAR',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_runs_list(): void
    {
        $this->makeRun();
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/co-reconciliation');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_returns_empty_for_new_org(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/co-reconciliation');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_run_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/co-reconciliation/' . $run->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/co-reconciliation/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Reconcile Assessment — validation
    // -------------------------------------------------------------------------

    public function test_reconcile_assessment_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/co-reconciliation/reconcile-assessment', []);

        $response->assertStatus(422);
    }

    public function test_reconcile_assessment_validates_fiscal_year_length(): void
    {
        $cycle = CoAssessmentCycle::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Cycle',
            'cycle_type'      => 'assessment',
            'fiscal_year'     => 2025,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/co-reconciliation/reconcile-assessment', [
                'assessment_cycle_id' => $cycle->id,
                'fiscal_year'         => '25',    // must be 4 chars
                'period'              => '01',
            ]);

        $response->assertStatus(422);
    }

    public function test_reconcile_assessment_validates_period_length(): void
    {
        $cycle = CoAssessmentCycle::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Cycle 2',
            'cycle_type'      => 'assessment',
            'fiscal_year'     => 2025,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/co-reconciliation/reconcile-assessment', [
                'assessment_cycle_id' => $cycle->id,
                'fiscal_year'         => '2025',
                'period'              => '1',     // must be 2 chars
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Reconcile Distribution — validation
    // -------------------------------------------------------------------------

    public function test_reconcile_distribution_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/co-reconciliation/reconcile-distribution', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/co-reconciliation')->assertStatus(401);
    }
}

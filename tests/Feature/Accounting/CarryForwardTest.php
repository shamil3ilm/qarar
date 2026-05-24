<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CarryForwardRun;
use App\Models\Accounting\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CarryForwardTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClosedFiscalYear(int $year = 2024): FiscalYear
    {
        return FiscalYear::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY ' . $year,
            'start_date'      => "{$year}-01-01",
            'end_date'        => "{$year}-12-31",
            'is_closed'       => true,
            'is_current'      => false,
        ]);
    }

    private function makeOpenFiscalYear(int $year = 2025): FiscalYear
    {
        return FiscalYear::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY ' . $year,
            'start_date'      => "{$year}-01-01",
            'end_date'        => "{$year}-12-31",
            'is_closed'       => false,
            'is_current'      => true,
        ]);
    }

    private function makeCompletedRun(FiscalYear $from, FiscalYear $to): CarryForwardRun
    {
        return CarryForwardRun::create([
            'organization_id'      => $this->organization->id,
            'from_fiscal_year_id'  => $from->id,
            'to_fiscal_year_id'    => $to->id,
            'run_type'             => CarryForwardRun::RUN_TYPE_BOTH,
            'status'               => CarryForwardRun::STATUS_COMPLETED,
            'accounts_processed'   => 5,
            'total_amount_carried' => 100000.00,
            'executed_by'          => $this->user->id,
            'executed_at'          => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    public function test_execute_rejects_open_source_fiscal_year(): void
    {
        $fromFy = $this->makeOpenFiscalYear(2024); // not closed
        $toFy   = $this->makeOpenFiscalYear(2025);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', [
                'from_fiscal_year_id' => $fromFy->id,
                'to_fiscal_year_id'   => $toFy->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_execute_rejects_same_fiscal_year(): void
    {
        $fy = $this->makeClosedFiscalYear(2024);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', [
                'from_fiscal_year_id' => $fy->id,
                'to_fiscal_year_id'   => $fy->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_execute_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', []);

        $response->assertStatus(422);
    }

    public function test_execute_rejects_invalid_run_type(): void
    {
        $fromFy = $this->makeClosedFiscalYear(2024);
        $toFy   = $this->makeOpenFiscalYear(2025);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', [
                'from_fiscal_year_id' => $fromFy->id,
                'to_fiscal_year_id'   => $toFy->id,
                'run_type'            => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    public function test_execute_rejects_fiscal_year_from_another_organization(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();

        $fromFy = $this->makeClosedFiscalYear(2024);
        // This FY exists in DB but is invisible via global scope → findOrFail → 404
        $toFy   = FiscalYear::factory()->create([
            'organization_id' => $otherOrg->id,
            'name'            => 'FY 2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', [
                'from_fiscal_year_id' => $fromFy->id,
                'to_fiscal_year_id'   => $toFy->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_execute_succeeds_with_closed_source_year(): void
    {
        $fromFy = $this->makeClosedFiscalYear(2024);
        $toFy   = $this->makeOpenFiscalYear(2025);

        // The carry forward works with no balance-sheet accounts → completes with 0 accounts
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/carry-forward/execute', [
                'from_fiscal_year_id' => $fromFy->id,
                'to_fiscal_year_id'   => $toFy->id,
                'run_type'            => 'both',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertSame(CarryForwardRun::STATUS_COMPLETED, $data['status']);
        $this->assertSame($fromFy->id, $data['from_fiscal_year_id']);
        $this->assertSame($toFy->id, $data['to_fiscal_year_id']);
    }

    // -------------------------------------------------------------------------
    // Status
    // -------------------------------------------------------------------------

    public function test_status_returns_run_details(): void
    {
        $fromFy = $this->makeClosedFiscalYear(2024);
        $toFy   = $this->makeOpenFiscalYear(2025);
        $run    = $this->makeCompletedRun($fromFy, $toFy);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/carry-forward/' . $run->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.status', CarryForwardRun::STATUS_COMPLETED);
    }

    public function test_status_returns_404_for_unknown_run(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/carry-forward/nonexistent-uuid');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/carry-forward/execute')->assertStatus(401);
    }
}
